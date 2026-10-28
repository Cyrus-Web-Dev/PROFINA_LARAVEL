<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identitas modul ini di dalam PROFINA
    |--------------------------------------------------------------------------
    | module : kode modul (sdm | payroll | mkp | marketing). Harus sama dengan kode di Portal (Core).
    | secret : kunci khusus modul ini. Dibuat Portal lewat `php artisan profina:provision`
    |          (diturunkan dari kunci induk, jadi modul lain tidak bisa memalsukan token modul ini).
    */
    'module' => env('PROFINA_MODULE'),
    'secret' => env('PROFINA_SSO_SECRET'),
    'core_url' => rtrim((string) env('PROFINA_CORE_URL', 'http://core.localhost:8000'), '/'),

    // Alamat Portal untuk panggilan SERVER-ke-server (introspeksi). Di development dibiarkan 127.0.0.1 supaya
    // tidak bergantung pada resolusi nama *.localhost di PHP/Windows. Di production samakan dengan core_url.
    'core_internal_url' => rtrim((string) env('PROFINA_CORE_INTERNAL_URL', env('PROFINA_CORE_URL', 'http://127.0.0.1:8000')), '/'),

    // Umur token masuk (detik). Token hanya boleh dipakai SEKALI.
    'token_ttl' => (int) env('PROFINA_TOKEN_TTL', 60),

    // Seberapa sering (detik) modul menanyakan ke Portal apakah akses user masih berlaku.
    // Ini yang membuat pencabutan akses / logout Portal berlaku di modul dalam hitungan detik-menit.
    'revalidate_seconds' => (int) env('PROFINA_REVALIDATE_SECONDS', 60),

    // Bila Portal tidak terjangkau, sesi yang sudah berjalan masih dibiarkan selama ini (detik), lalu dipaksa keluar.
    'grace_seconds' => (int) env('PROFINA_GRACE_SECONDS', 300),

    // Halaman awal modul setelah masuk.
    'home' => env('PROFINA_HOME', '/dashboard'),

    // Model user & guard Spatie. Null = pakai config('auth.providers.users.model') dan guard "web".
    'user_model' => null,
    'guard' => 'web',

    // Kelas invokable (opsional) untuk laporan kesehatan modul di dashboard Portal: fn(): array.
    'metrics' => null,

    // Kelas invokable (opsional) yang dipanggil setelah user berhasil masuk: fn($user, array $claims): void.
    'after_login' => null,
];
