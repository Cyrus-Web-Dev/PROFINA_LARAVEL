<?php

return [

    /*
    | Algoritma hash password: Argon2id (pemenang Password Hashing Competition,
    | tahan serangan GPU/ASIC karena memory-hard, dan tahan side-channel).
    | Butuh PHP dengan dukungan Argon2 (cek: php -r 'var_dump(defined("PASSWORD_ARGON2ID"));').
    */
    'driver' => env('HASH_DRIVER', 'argon2id'),

    // Tidak dipakai lagi untuk password baru; dipertahankan hanya agar hash bcrypt lama
    // masih bisa diverifikasi dan diubah otomatis ke Argon2id saat pemiliknya login.
    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', false),
    ],

    /*
    | Parameter Argon2id (dipakai driver "argon2id"; Laravel membaca blok ini).
    |   memory  : KiB. 65536 = 64 MiB per proses hashing.
    |   time    : jumlah iterasi.
    |   threads : paralelisme.
    | Patokan minimum OWASP: memory 19 MiB (19456), time 2, threads 1. Naikkan bila server kuat,
    | turunkan memory bila hosting terbatas & banyak login bersamaan.
    | Mengubah angka ini aman: hash lama otomatis diperbarui saat pemiliknya login berikutnya.
    */
    'argon' => [
        'memory' => (int) env('HASH_ARGON_MEMORY', 65536),
        'threads' => (int) env('HASH_ARGON_THREADS', 1),
        'time' => (int) env('HASH_ARGON_TIME', 4),

        // WAJIB false selama masih ada hash bcrypt di tabel users. Bila true, Laravel melempar
        // exception saat memeriksa hash non-Argon2id sehingga user lama tidak bisa login.
        // Setelah semua user termigrasi (lihat `php artisan mkp:hash-status`), ubah jadi true.
        'verify' => env('HASH_VERIFY', false),
    ],

];
