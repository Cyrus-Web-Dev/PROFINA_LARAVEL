<?php

return [

    'name' => env('APP_NAME', 'SDM HRIS'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://127.0.0.1:8000'),

    // Zona waktu Indonesia (WIB). Tanpa ini, tanggal "hari ini" di server memakai UTC
    // sehingga dini hari (00:00-07:00 WIB) tanggalnya masih "kemarin" - penting untuk absensi.
    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    'locale' => env('APP_LOCALE', 'id'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'id_ID'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
