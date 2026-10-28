<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifikasi anti-bot Cloudflare Turnstile (gratis, tanpa teka-teki gambar). Aktif HANYA bila
 * TURNSTILE_SITE_KEY dan TURNSTILE_SECRET_KEY diisi di .env; bila kosong, semua pemeriksaan dilewati.
 * Untuk uji lokal, Cloudflare menyediakan key dummy (lihat README).
 *
 * Kebijakan gangguan: bila server Cloudflare tak terjangkau, verifikasi DILEWATI (fail-open) agar form tidak
 * mati total; peringatan dicatat di log. Pertahanan lain (rate limit, OTP email) tetap berlaku.
 */
class Turnstile
{
    public function enabled(): bool
    {
        return config('pay.security.turnstile.site_key') && config('pay.security.turnstile.secret_key');
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }
        if (! $token) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(8)->connectTimeout(4)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('pay.security.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('[Turnstile] Tidak bisa menghubungi Cloudflare, verifikasi dilewati: '.$e->getMessage());

            return true;
        }
    }

    /** @throws ValidationException */
    public function assertPassed(Request $request, string $errorKey = 'email'): void
    {
        if (! $this->verify($request->input('turnstile_token'), $request->ip())) {
            throw ValidationException::withMessages([$errorKey => 'Verifikasi anti-bot gagal. Muat ulang halaman lalu coba lagi.']);
        }
    }
}
