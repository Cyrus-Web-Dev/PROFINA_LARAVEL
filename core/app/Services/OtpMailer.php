<?php

namespace App\Services;

use App\Exceptions\GmailSendException;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim kode OTP lewat Gmail API dan melaporkan hasilnya secara JUJUR.
 */
class OtpMailer
{
    public function __construct(private GmailApiService $gmail)
    {
    }

    /**
     * @return array{0: bool, 1: ?string, 2: ?string} [berhasil, pesan_error_untuk_user, catatan_untuk_user]
     *         catatan_untuk_user terisi bila kode tidak dikirim lewat email tapi dicatat ke log (mode lokal).
     */
    public function send(string $to, ?string $name, string $subject, string $intro, string $code): array
    {
        $ttl = (int) config('gmail.otp_ttl_minutes');

        $html = view('emails.otp', [
            'name' => $name,
            'intro' => $intro,
            'code' => $code,
            'ttlMinutes' => $ttl,
        ])->render();

        $text = "Halo".($name ? " {$name}" : '').",\n\n{$intro}\n\nKODE: {$code}\n\nBerlaku {$ttl} menit. Jangan bagikan kode ini kepada siapa pun.";

        try {
            $this->gmail->send($to, $name, $subject, $html, $text);

            return [true, null, null];
        } catch (GmailSendException $e) {
            Log::warning('[OTP] Email OTP gagal terkirim: '.$e->getMessage());

            if ($this->localFallbackAllowed()) {
                Log::warning("[OTP-LOKAL] Kode untuk {$to}: {$code} (berlaku {$ttl} menit)");

                return [true, null, 'Gmail belum siap ('.$e->userMessage().') — mode lokal: kode dicatat di storage/logs/laravel.log.'];
            }

            return [false, $e->userMessage(), null];
        }
    }

    private function localFallbackAllowed(): bool
    {
        return config('gmail.local_log_fallback') && app()->environment('local') && config('app.debug');
    }
}
