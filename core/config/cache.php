<?php

// Default "file": tidak butuh tabel database, jadi kode OTP & rate limiter tetap jalan
// walaupun migrasi belum dijalankan, dan lebih cepat daripada driver database.
return [

    'default' => env('CACHE_STORE', 'file'),

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

    ],

    'prefix' => env('CACHE_PREFIX', 'profina_core_cache_'),

];
