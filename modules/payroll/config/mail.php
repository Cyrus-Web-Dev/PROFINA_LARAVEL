<?php

return [

    // Semua email sistem (2FA, verifikasi register, reset password) lewat Gmail API.
    'default' => env('MAIL_MAILER', 'gmail_api'),

    'mailers' => [

        // PENTING: entri ini WAJIB ada. Tanpa ini Laravel melempar
        // "Mailer [gmail_api] is not defined" (penyebab forgot-password gagal di versi lama).
        // Transport-nya didaftarkan di App\Providers\AppServiceProvider.
        'gmail_api' => [
            'transport' => 'gmail_api',
        ],

        // Alternatif untuk testing tanpa Gmail: MAIL_MAILER=log -> isi email ditulis ke storage/logs/laravel.log
        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@paybridge.local'),
        'name' => env('MAIL_FROM_NAME', 'PAYBRIDGE'),
    ],

];
