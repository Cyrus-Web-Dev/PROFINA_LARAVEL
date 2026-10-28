<?php

return [

    // Toleransi keterlambatan (menit) sebelum status absensi otomatis "terlambat".
    'late_tolerance_minutes' => (int) env('SDM_LATE_TOLERANCE_MINUTES', 15),
    'work_start_time' => env('SDM_WORK_START_TIME', '08:00'),
    'work_end_time' => env('SDM_WORK_END_TIME', '17:00'),

    // Berapa hari sebelum dokumen (kontrak/sertifikat) kedaluwarsa mulai ditandai "segera berakhir".
    'document_expiry_reminder_days' => (int) env('SDM_DOCUMENT_EXPIRY_REMINDER_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Komponen BPJS (persentase dari upah, sesuai aturan yang berlaku umum).
    | Nilai ini bisa disesuaikan dari .env tanpa mengubah kode kalau pemerintah
    | mengubah regulasi.
    |--------------------------------------------------------------------------
    */
    'bpjs' => [
        'kesehatan' => [
            'employee_pct' => (float) env('SDM_BPJS_KESEHATAN_EMPLOYEE_PCT', 1),
            'company_pct' => (float) env('SDM_BPJS_KESEHATAN_COMPANY_PCT', 4),
            // Batas atas upah yang dipakai sbg dasar perhitungan BPJS Kesehatan.
            'salary_cap' => (float) env('SDM_BPJS_KESEHATAN_SALARY_CAP', 12000000),
        ],
        'jht' => [ // Jaminan Hari Tua
            'employee_pct' => (float) env('SDM_BPJS_JHT_EMPLOYEE_PCT', 2),
            'company_pct' => (float) env('SDM_BPJS_JHT_COMPANY_PCT', 3.7),
        ],
        'jkk' => [ // Jaminan Kecelakaan Kerja (ditanggung perusahaan penuh, persentase bervariasi per risiko)
            'company_pct' => (float) env('SDM_BPJS_JKK_COMPANY_PCT', 0.24),
        ],
        'jkm' => [ // Jaminan Kematian (ditanggung perusahaan penuh)
            'company_pct' => (float) env('SDM_BPJS_JKM_COMPANY_PCT', 0.3),
        ],
        'jp' => [ // Jaminan Pensiun
            'employee_pct' => (float) env('SDM_BPJS_JP_EMPLOYEE_PCT', 1),
            'company_pct' => (float) env('SDM_BPJS_JP_COMPANY_PCT', 2),
            'salary_cap' => (float) env('SDM_BPJS_JP_SALARY_CAP', 10547400),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PPh 21 - lapisan tarif progresif & PTKP (per tahun).
    | PENTING: ini estimasi untuk kebutuhan payroll internal, BUKAN pengganti
    | konsultasi pajak resmi. Selalu verifikasi ke konsultan/DJP untuk kepatuhan penuh.
    |--------------------------------------------------------------------------
    */
    'pph21' => [
        'brackets' => [ // [batas_atas_tahunan, tarif]
            [60000000, 0.05],
            [250000000, 0.15],
            [500000000, 0.25],
            [5000000000, 0.30],
            [PHP_INT_MAX, 0.35],
        ],
        // PTKP tahunan per status (Penghasilan Tidak Kena Pajak)
        'ptkp' => [
            'TK/0' => 54000000, 'TK/1' => 58500000, 'TK/2' => 63000000, 'TK/3' => 67500000,
            'K/0' => 58500000, 'K/1' => 63000000, 'K/2' => 67500000, 'K/3' => 72000000,
        ],
        // Biaya jabatan: 5% dari penghasilan bruto, maks per bulan
        'jabatan_cost_pct' => 0.05,
        'jabatan_cost_cap_monthly' => 500000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Hari kerja & hari libur
    |--------------------------------------------------------------------------
    */
    // 5 = Senin-Jumat (Sabtu & Minggu libur), 6 = Senin-Sabtu (hanya Minggu libur).
    'work_days_per_week' => (int) env('SDM_WORK_DAYS_PER_WEEK', 5),

    'holidays' => [
        // Ambil hari libur nasional & cuti bersama dari API/dataset publik (bukan API resmi pemerintah,
        // lihat README). Bila mati/gagal, otomatis pakai cache terakhir lalu file cadangan bawaan.
        'enabled' => (bool) env('SDM_HOLIDAY_API', true),
        'source_url' => env('SDM_HOLIDAY_SOURCE_URL', 'https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.min.json'),
        'cache_hours' => (int) env('SDM_HOLIDAY_CACHE_HOURS', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geofencing absensi (opsional). Kosongkan SDM_OFFICE_LAT/LNG untuk menonaktifkan.
    |--------------------------------------------------------------------------
    */
    'office' => [
        'lat' => env('SDM_OFFICE_LAT') !== null && env('SDM_OFFICE_LAT') !== '' ? (float) env('SDM_OFFICE_LAT') : null,
        'lng' => env('SDM_OFFICE_LNG') !== null && env('SDM_OFFICE_LNG') !== '' ? (float) env('SDM_OFFICE_LNG') : null,
        'radius_m' => (int) env('SDM_OFFICE_RADIUS_M', 200),
        // true = check-in di luar radius DITOLAK; false = tetap diterima tapi ditandai di catatan.
        'enforce' => (bool) env('SDM_GEOFENCE_ENFORCE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifikasi tambahan (opsional, semuanya nonaktif bila token kosong)
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'whatsapp' => [ // Fonnte - gateway WhatsApp pihak ketiga (bukan WhatsApp Cloud API resmi)
            'token' => env('FONNTE_TOKEN'),
            'admin_number' => env('SDM_WHATSAPP_ADMIN_NUMBER'), // nomor HR yang menerima peringatan
        ],
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        ],
    ],

    'registration_enabled' => (bool) env('SDM_REGISTRATION_ENABLED', true),

    'security' => [
        // off | report-only | enforce  (Content-Security-Policy). Mulai dari report-only.
        'csp' => env('SDM_CSP', 'report-only'),
        'force_https' => (bool) env('SDM_FORCE_HTTPS', false),
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],


    'admin' => [
        'name' => env('SDM_ADMIN_NAME', 'Administrator HR'),
        'email' => env('SDM_ADMIN_EMAIL'),
        'password' => env('SDM_ADMIN_PASSWORD'),
    ],

    'backup' => [
        'keep' => (int) env('SDM_BACKUP_KEEP', 10),
        'pg_dump_path' => env('SDM_PG_DUMP_PATH'),
        'mysqldump_path' => env('SDM_MYSQLDUMP_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrasi Payroll (PAYBRIDGE)
    |--------------------------------------------------------------------------
    | external=true : modul payroll bawaan SDM DIKUNCI (hitung/lunas/ubah item dinonaktifkan); gaji dihitung & disetujui di PAYBRIDGE.
    |                 Data lama tetap bisa dilihat. Aktifkan setelah PAYBRIDGE berjalan.
    */
    'payroll' => [
        'external' => (bool) env('SDM_PAYROLL_EXTERNAL', false),
        'webhook_secret' => env('PAYROLL_WEBHOOK_SECRET'),   // rahasia HMAC webhook PAYBRIDGE -> SDM
        'portal_url' => env('PAYROLL_PORTAL_URL'),            // mis. http://127.0.0.1:8003
        'count_unrecorded_as_alpha' => (bool) env('SDM_COUNT_UNRECORDED_AS_ALPHA', false),
        'api_rate_limit' => (int) env('SDM_PAYROLL_API_RATE_LIMIT', 120),
        'webhook_timeout' => (int) env('SDM_PAYROLL_WEBHOOK_TIMEOUT', 8),
    ],

];
