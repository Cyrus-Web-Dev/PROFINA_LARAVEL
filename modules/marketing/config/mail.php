<?php

return [

    // Semua email sistem (2FA, verifikasi register, reset password) lewat Gmail API.
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

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
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@profina.local'),
        'name' => env('MAIL_FROM_NAME', 'PROFINA'),
    ],

];
