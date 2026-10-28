<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan HTTP standar. Tidak menggantikan konfigurasi web-server (nginx/Apache),
 * tapi lapisan pertahanan tambahan yang tetap aktif walau konfigurasi server default.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cegah halaman kita ditampilkan dalam <iframe> situs lain (anti clickjacking).
        $response->headers->set('X-Frame-Options', 'DENY');

        // Cegah browser "menebak" tipe file dari isinya (mis. file .txt dijalankan sbg .html).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Jangan bocorkan URL penuh (bisa mengandung token) ke situs lain saat user klik link keluar.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Matikan akses ke API browser sensitif yang tidak dipakai aplikasi ini secara default,
        // KECUALI geolocation (dipakai fitur absen check-in/out).
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=()');

        // HSTS: paksa HTTPS selama 1 tahun, HANYA dikirim kalau koneksi memang sudah HTTPS
        // (mengirim ini lewat HTTP di localhost akan mengunci browser ke HTTPS yang belum ada -> jangan).
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy: 'off' (nonaktif) | 'report-only' (dicatat, TIDAK memblokir apa pun -
        // aman dipakai untuk memantau dulu) | 'enforce' (benar-benar memblokir resource di luar daftar).
        // Mulai dari 'report-only', pantau Console browser, baru pindah ke 'enforce' kalau sudah bersih.
        $mode = config('hr.security.csp', 'off');
        if ($mode === 'report-only' || $mode === 'enforce') {
            $policy = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "frame-src https://challenges.cloudflare.com",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]);
            $header = $mode === 'enforce' ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
            $response->headers->set($header, $policy);
        }

        return $response;
    }
}
