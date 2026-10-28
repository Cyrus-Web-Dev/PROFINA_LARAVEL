<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver hashing password
    |--------------------------------------------------------------------------
    | argon2id dipilih sebagai default (BUKAN bcrypt): dirancang tahan terhadap serangan akselerasi
    | GPU/ASIC/FPGA (bcrypt hanya tahan CPU-time, tidak memory-hard), dan merupakan pemenang Password
    | Hashing Competition serta direkomendasikan OWASP sebagai pilihan pertama saat ini.
    |
    | Wajib PHP dikompilasi dengan dukungan Argon2 (umum di PHP 7.2+ / 8.x modern, termasuk XAMPP
    | terbaru). Cek dengan: php -r "var_dump(defined('PASSWORD_ARGON2ID'));" - harus bool(true).
    | Bila false, set HASH_DRIVER=bcrypt di .env sebagai fallback sementara sampai PHP diperbarui.
    |--------------------------------------------------------------------------
    */
    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        // Dipakai HANYA sebagai fallback (HASH_DRIVER=bcrypt) atau untuk memverifikasi hash lama
        // yang mungkin masih tersisa (mis. hasil migrasi data lama) - rehash otomatis ada di
        // AuthenticatedSessionController & Api\AuthController.
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
    ],

    // Dipakai untuk driver "argon" (Argon2i) MAUPUN "argon2id" - hanya algoritmenya yang beda,
    // parameter memori/waktu sama. Nilai di bawah = rekomendasi minimum OWASP utk argon2id:
    // memory 19 MiB (19456 KiB), time 2, threads 1. Kita naikkan sedikit (memory 37 MiB, time 3)
    // untuk margin keamanan ekstra - server modern menanganinya dalam hitungan puluhan milidetik.
    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 37888), // KiB (~37 MiB)
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 3),
        'verify' => env('HASH_VERIFY', true),
    ],

];
