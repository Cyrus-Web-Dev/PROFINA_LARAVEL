<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sumber kredensial (dicoba berurutan; yang pertama lengkap dipakai)
    |--------------------------------------------------------------------------
    | 1. File storage/app/credentials/credentials.json + token.json
    |    -> SAMA seperti proyek Inventaris. Cukup salin kedua file itu ke folder tsb.
    | 2. Variabel .env: GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN
    | Panduan lengkap: README.md, bagian "Menyiapkan Gmail API".
    */

    // Client ID/Secret dari Google Cloud Console (OAuth client). GOOGLE_* dipertahankan
    // supaya .env lama tetap kompatibel.
    'client_id' => env('GMAIL_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
    'client_secret' => env('GMAIL_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
    'refresh_token' => env('GMAIL_REFRESH_TOKEN'),

    // Opsional: path file cacert.pem bila muncul error SSL "cURL error 60" (umum di Windows/XAMPP).
    'ca_bundle' => env('GMAIL_CA_BUNDLE'),

    'credentials_path' => storage_path('app/credentials/credentials.json'),
    'token_path' => storage_path('app/credentials/token.json'),

    'from_name' => env('MAIL_FROM_NAME', 'PROFINA'),

    // Batas waktu HTTP ke Google (detik). Tanpa batas, koneksi macet membuat halaman
    // login/register "loading" puluhan detik.
    'http_timeout' => (int) env('GMAIL_HTTP_TIMEOUT', 15),
    'http_connect_timeout' => (int) env('GMAIL_HTTP_CONNECT_TIMEOUT', 8),

    /*
    | Mode lokal: kalau Gmail belum siap DAN APP_ENV=local DAN APP_DEBUG=true, kode OTP
    | ditulis ke storage/logs/laravel.log sehingga kamu tetap bisa login/daftar untuk
    | pertama kali (mengatasi masalah "ayam-telur": butuh login untuk setup Gmail, tapi
    | login butuh email). Otomatis MATI di production.
    */
    'local_log_fallback' => (bool) env('GMAIL_LOCAL_LOG_FALLBACK', true),

    // --- Pengaturan OTP ---
    'otp_length' => 6,
    'otp_ttl_minutes' => 10,
    'otp_max_attempts' => 5,
    'otp_resend_cooldown_seconds' => 60,

];
