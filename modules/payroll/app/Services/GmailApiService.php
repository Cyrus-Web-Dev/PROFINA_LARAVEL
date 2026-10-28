<?php

namespace App\Services;

use App\Exceptions\GmailSendException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Pengirim email lewat Gmail API (OAuth2).
 *
 * Kredensial dicari berurutan (sama seperti proyek Inventaris yang sudah terbukti jalan):
 *   1. storage/app/credentials/credentials.json + token.json
 *   2. .env: GMAIL_CLIENT_ID / GMAIL_CLIENT_SECRET / GMAIL_REFRESH_TOKEN
 * Cara menyiapkannya: lihat README bagian "Menyiapkan Gmail API".
 * Cek cepat dari terminal: php artisan pay:mail-test email@tujuan.com
 *
 * Semua kegagalan dilempar sebagai GmailSendException (TIDAK ditelan diam-diam seperti
 * versi lama), dengan pesan yang menjelaskan penyebab & solusinya.
 */
class GmailApiService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SEND_URL = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    // ---------------------------------------------------------------------
    //  Kredensial
    // ---------------------------------------------------------------------

    public function isConnected(): bool
    {
        return $this->resolveCredentials() !== null;
    }

    /**
     * @param  array<int,string>  $notes  diisi alasan mengapa suatu sumber dilewati (untuk halaman diagnostik)
     * @return array{source:string,client_id:string,client_secret:string,refresh_token:string,access_token:?string,expires_at:int,email:?string}|null
     */
    public function resolveCredentials(array &$notes = []): ?array
    {
        // 1. credentials.json + token.json (cara proyek Inventaris)
        $tokenPath = config('gmail.token_path');
        $credPath = config('gmail.credentials_path');
        $hasToken = is_readable($tokenPath);
        $hasCred = is_readable($credPath);

        if ($hasToken && $hasCred) {
            $token = json_decode((string) file_get_contents($tokenPath), true);
            $cred = json_decode((string) file_get_contents($credPath), true);

            if (! is_array($token) || ! is_array($cred)) {
                $notes[] = 'token.json atau credentials.json bukan JSON yang valid (file rusak / terpotong).';
            } else {
                $cfg = $cred['web'] ?? $cred['installed'] ?? $cred;
                if (! empty($cfg['client_id']) && ! empty($cfg['client_secret']) && ! empty($token['refresh_token'])) {
                    return [
                        'source' => 'file',
                        'client_id' => $cfg['client_id'],
                        'client_secret' => $cfg['client_secret'],
                        'refresh_token' => $token['refresh_token'],
                        'access_token' => $token['access_token'] ?? null,
                        'expires_at' => (int) (($token['created'] ?? 0) + ($token['expires_in'] ?? 0)),
                        'email' => null,
                    ];
                }
                $notes[] = 'token.json tidak berisi refresh_token, atau credentials.json tidak berisi client_id/client_secret.';
            }
        } elseif ($hasToken || $hasCred) {
            $notes[] = 'Hanya satu dari dua file yang ada. Butuh KEDUANYA: credentials.json dan token.json di storage/app/credentials/.';
        }

        // 2. Variabel .env
        if (config('gmail.client_id') && config('gmail.client_secret') && config('gmail.refresh_token')) {
            return [
                'source' => 'env',
                'client_id' => (string) config('gmail.client_id'),
                'client_secret' => (string) config('gmail.client_secret'),
                'refresh_token' => (string) config('gmail.refresh_token'),
                'access_token' => null,
                'expires_at' => 0,
                'email' => null,
            ];
        }

        return null;
    }

    /**
     * Ringkasan kondisi konfigurasi (dipakai perintah `php artisan pay:mail-test`).
     */
    public function diagnose(): array
    {
        $notes = [];
        $cred = $this->resolveCredentials($notes);

        return [
            'ready' => $cred !== null,
            'source' => $cred['source'] ?? null,
            'email' => $cred['email'] ?? null,
            'notes' => $notes,
            'files' => [
                'credentials' => is_readable(config('gmail.credentials_path')),
                'token' => is_readable(config('gmail.token_path')),
            ],
            'has_client' => (bool) (config('gmail.client_id') && config('gmail.client_secret')),
        ];
    }

    // ---------------------------------------------------------------------
    //  Mengirim email
    // ---------------------------------------------------------------------

    /**
     * Kirim email HTML. Melempar GmailSendException bila gagal.
     */
    /** @param array<int,array{0:string,1:string,2:string}> $attachments [nama_file, isi_biner, mime] */
    public function send(string $to, ?string $toName, string $subject, string $htmlBody, ?string $textBody = null, array $attachments = []): void
    {
        $cred = $this->requireCredentials();

        try {
            $email = (new Email())
                ->from(new Address($cred['email'] ?: (string) config('mail.from.address'), (string) config('gmail.from_name')))
                ->to(new Address($to, (string) $toName))
                ->subject($subject)
                ->html($htmlBody)
                ->text($textBody ?? trim(strip_tags($htmlBody)));
            foreach ($attachments as [$fname, $bin, $type]) {
                $email->attach($bin, $fname, $type);
            }

            $mime = $email->toString();
        } catch (Throwable $e) {
            throw new GmailSendException('Alamat email tujuan tidak valid atau pesan gagal disusun.', $e->getMessage(), $e);
        }

        $this->deliver($mime, $cred);
    }

    /**
     * Kirim pesan MIME mentah (dipakai oleh Laravel Mail, mis. link reset password).
     */
    public function sendRawMime(string $mime): void
    {
        $this->deliver($mime, $this->requireCredentials());
    }

    /**
     * @return array{source:string,client_id:string,client_secret:string,refresh_token:string,access_token:?string,expires_at:int,email:?string}
     */
    private function requireCredentials(): array
    {
        $notes = [];
        $cred = $this->resolveCredentials($notes);

        if (! $cred) {
            Log::error('[GmailApi] Kredensial tidak ditemukan.', ['catatan' => $notes]);

            throw new GmailSendException(
                'Pengirim email (Gmail API) belum dikonfigurasi. Salin credentials.json + token.json ke storage/app/credentials/ '
                .'(atau isi GMAIL_* di .env). Langkah lengkapnya ada di README, bagian "Menyiapkan Gmail API".'.($notes ? ' Catatan: '.implode(' ', $notes) : '')
            );
        }

        return $cred;
    }

    private function deliver(string $mime, array $cred): void
    {
        $raw = rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $token = $this->accessToken($cred, forceRefresh: $attempt > 0);

            try {
                $response = $this->http()->withToken($token)->asJson()->post(self::SEND_URL, ['raw' => $raw]);
            } catch (ConnectionException $e) {
                throw $this->connectionError($e);
            }

            if ($response->successful()) {
                return;
            }

            // access token yang ter-cache ternyata sudah tidak berlaku -> ambil yang baru sekali lagi
            if ($response->status() === 401 && $attempt === 0) {
                Cache::forget($this->cacheKey($cred['refresh_token']));

                continue;
            }

            throw $this->sendError($response);
        }
    }

    private function accessToken(array $cred, bool $forceRefresh = false): string
    {
        $key = $this->cacheKey($cred['refresh_token']);

        if (! $forceRefresh) {
            if ($cached = Cache::get($key)) {
                return $cached;
            }
            if (! empty($cred['access_token']) && $cred['expires_at'] > time() + 90) {
                return $cred['access_token'];
            }
        }

        try {
            $response = $this->http()->asForm()->post(self::TOKEN_URL, [
                'client_id' => $cred['client_id'],
                'client_secret' => $cred['client_secret'],
                'refresh_token' => $cred['refresh_token'],
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException $e) {
            throw $this->connectionError($e);
        }

        if ($response->failed() || ! $response->json('access_token')) {
            throw $this->tokenError($response);
        }

        $data = $response->json();
        $expiresIn = (int) ($data['expires_in'] ?? 3600);
        Cache::put($key, $data['access_token'], max(60, $expiresIn - 120));

        $this->persistAccessToken($cred, $data['access_token'], $expiresIn);

        return $data['access_token'];
    }

    /** Simpan access token terbaru ke sumbernya (supaya sumber itu selalu up to date). Gagal menyimpan tidak fatal. */
    private function persistAccessToken(array $cred, string $accessToken, int $expiresIn): void
    {
        try {
            if ($cred['source'] === 'file' && is_writable(config('gmail.token_path'))) {
                $token = json_decode((string) file_get_contents(config('gmail.token_path')), true) ?: [];
                $token['access_token'] = $accessToken;
                $token['expires_in'] = $expiresIn;
                $token['created'] = time();
                file_put_contents(config('gmail.token_path'), json_encode($token, JSON_PRETTY_PRINT));
            }
        } catch (Throwable $e) {
            Log::notice('[GmailApi] Gagal menyimpan access token terbaru: '.$e->getMessage());
        }
    }

    private function cacheKey(string $refreshToken): string
    {
        return 'gmail:access_token:'.hash('sha256', $refreshToken);
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('gmail.http_timeout', 15))
            ->connectTimeout((int) config('gmail.http_connect_timeout', 8))
            ->acceptJson()
            ->withOptions(['verify' => config('gmail.ca_bundle') ?: true]);
    }

    // ---------------------------------------------------------------------
    //  Pesan error yang bisa ditindaklanjuti
    // ---------------------------------------------------------------------

    private function connectionError(ConnectionException $e): GmailSendException
    {
        $msg = $e->getMessage();
        Log::error('[GmailApi] Koneksi ke Google gagal: '.$msg);

        if (str_contains($msg, 'cURL error 60') || str_contains($msg, 'SSL certificate')) {
            return new GmailSendException(
                'Koneksi ke Google ditolak karena sertifikat SSL tidak dikenali (masalah umum PHP di Windows/XAMPP). '
                .'Solusi: unduh https://curl.se/ca/cacert.pem lalu isi curl.cainfo di php.ini, atau isi GMAIL_CA_BUNDLE di .env dengan path file itu.',
                $msg,
                $e
            );
        }
        if (str_contains($msg, 'timed out') || str_contains($msg, 'cURL error 28')) {
            return new GmailSendException('Koneksi ke Google timeout. Periksa koneksi internet / firewall / antivirus, lalu coba lagi.', $msg, $e);
        }
        if (str_contains($msg, 'Could not resolve host') || str_contains($msg, 'cURL error 6')) {
            return new GmailSendException('Tidak bisa menemukan server Google (DNS). Periksa koneksi internet kamu.', $msg, $e);
        }

        return new GmailSendException('Tidak bisa terhubung ke server Google. Periksa koneksi internet kamu.', $msg, $e);
    }

    private function tokenError(Response $response): GmailSendException
    {
        $error = (string) $response->json('error');
        $desc = (string) $response->json('error_description');
        Log::error('[GmailApi] Gagal mendapatkan access token', ['status' => $response->status(), 'error' => $error, 'desc' => $desc]);

        $user = match ($error) {
            'invalid_grant' => 'Token Gmail ditolak Google (invalid_grant): sudah kedaluwarsa atau dicabut. '
                .'Penyebab tersering: aplikasi OAuth di Google Cloud masih berstatus "Testing" (token hangus tiap 7 hari), '
                .'atau client_id/secret tidak sesuai dengan token. Solusi: buat token baru lalu ganti token.json (lihat README, bagian "Menyiapkan Gmail API").',
            'invalid_client' => 'Client ID / Client Secret salah (invalid_client). Periksa credentials.json atau isi .env.',
            'redirect_uri_mismatch' => 'Redirect URI tidak cocok dengan yang terdaftar di Google Cloud Console.',
            default => 'Google menolak permintaan token'.($error ? " ({$error})" : '').'. '.Str::limit($desc, 160),
        };

        return new GmailSendException($user, "Token error {$error}: {$desc}");
    }

    private function sendError(Response $response): GmailSendException
    {
        $status = $response->status();
        $message = (string) $response->json('error.message');
        Log::error('[GmailApi] Gmail API menolak pengiriman', ['status' => $status, 'body' => Str::limit($response->body(), 600)]);

        $lower = strtolower($message);
        $user = match (true) {
            $status === 403 && (str_contains($lower, 'insufficient') || str_contains($lower, 'scope')) => 'Token tidak punya izin mengirim email. Buat ulang token dengan scope https://www.googleapis.com/auth/gmail.send.',
            $status === 403 && (str_contains($lower, 'has not been used') || str_contains($lower, 'disabled')) => 'Gmail API belum diaktifkan di project Google Cloud. Buka APIs & Services > Library > Gmail API > Enable.',
            $status === 400 && str_contains($lower, 'to header') => 'Alamat email tujuan tidak valid.',
            $status === 429 => 'Kuota pengiriman Gmail terlampaui. Coba lagi beberapa saat lagi.',
            default => "Gmail API menolak pengiriman (HTTP {$status})".($message ? ': '.Str::limit($message, 160) : '').'.',
        };

        return new GmailSendException($user, "Gmail send {$status}: {$message}");
    }
}
