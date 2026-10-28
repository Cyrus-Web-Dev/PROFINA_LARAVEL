<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Kosong = tidak ada origin luar yang diizinkan secara default (aman).
    // Isi lewat .env kalau ada aplikasi web terpisah (bukan mobile native) yang perlu memanggil API ini,
    // contoh: CORS_ALLOWED_ORIGINS=https://app-lain.perusahaan.com,https://portal.perusahaan.com
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
