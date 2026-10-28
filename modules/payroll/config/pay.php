<?php

/*
|--------------------------------------------------------------------------
| PAYBRIDGE - konfigurasi aplikasi
|--------------------------------------------------------------------------
| Tarif & batas REGULASI (BPJS, PPh 21, TER, PTKP, dst) TIDAK ada di sini. Itu disimpan di database
| (tabel regulation_parameters + tax_ter_rates) lengkap dengan tanggal berlaku, supaya perubahan
| aturan (mis. batas upah JP naik tiap 1 Maret) tercatat, bisa ditelusuri, dan tidak mengubah
| hasil payroll periode lama. Lihat menu "Regulasi".
|
| File ini hanya memuat PENGATURAN OPERASIONAL & AMBANG KONTROL.
*/
return [

    'registration_enabled' => (bool) env('PAY_REGISTRATION_ENABLED', true),

    'security' => [
        // off | report-only | enforce  (Content-Security-Policy). Mulai dari report-only.
        'csp' => env('PAY_CSP', 'report-only'),
        'force_https' => (bool) env('PAY_FORCE_HTTPS', false),
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],

    'admin' => [
        'name' => env('PAY_ADMIN_NAME', 'Administrator Payroll'),
        'email' => env('PAY_ADMIN_EMAIL'),
        'password' => env('PAY_ADMIN_PASSWORD'),
    ],

    'backup' => [
        'keep' => (int) env('PAY_BACKUP_KEEP', 10),
        'pg_dump_path' => env('PAY_PG_DUMP_PATH'),
        'mysqldump_path' => null,
    ],

    'notifications' => [
        'whatsapp' => [
            'token' => env('FONNTE_TOKEN'),
            'admin_number' => env('PAY_WHATSAPP_ADMIN_NUMBER'),
        ],
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrasi antar-sistem ERP
    |--------------------------------------------------------------------------
    | SDM  : PAYBRIDGE memanggil REST API SDM (tarik karyawan, absensi) dan menerima webhook dari SDM.
    | API  : REST API milik PAYBRIDGE yang dipakai Keuangan (MKP) dan SDM (lihat docs/INTEGRASI.md).
    */
    'integrations' => [
        'sdm' => [
            'url' => env('SDM_URL'),                       // contoh: http://127.0.0.1:8002/api/payroll/v1
            'token' => env('SDM_TOKEN'),                   // token klien "payroll" yang dibuat di SDM > Integrasi Payroll
            'webhook_secret' => env('SDM_WEBHOOK_SECRET'), // rahasia HMAC webhook SDM -> PAYBRIDGE
            'timeout' => (int) env('SDM_TIMEOUT', 15),
        ],
        'api' => [
            'rate_limit' => (int) env('PAY_API_RATE_LIMIT', 120),
            'webhook_timeout' => (int) env('PAY_WEBHOOK_TIMEOUT', 8),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambang kontrol anti-kecurangan (internal control)
    |--------------------------------------------------------------------------
    */
    'controls' => [
        // Segregation of duties: pembuat, pemeriksa, dan penyetuju HARUS orang berbeda. Jangan matikan di production.
        'sod_enforced' => (bool) env('PAY_SOD_ENFORCED', true),

        // Total gaji bersih satu putaran >= nilai ini (atau ada karyawan golongan eksekutif) wajib DUA penyetuju.
        'dual_approval_threshold' => (float) env('PAY_DUAL_APPROVAL_THRESHOLD', 500000000),

        // Selisih gaji kotor vs putaran reguler sebelumnya (tanpa perubahan kontrak) yang ditandai mencurigakan.
        'salary_jump_pct' => (float) env('PAY_SALARY_JUMP_PCT', 25),

        // Rekening yang diubah dalam N hari sebelum tanggal bayar wajib diverifikasi manual.
        'bank_change_window_days' => (int) env('PAY_BANK_CHANGE_WINDOW_DAYS', 30),

        // Input manual (bonus/tunjangan/koreksi) di atas nilai ini butuh penyetuju level Manager Payroll.
        'large_input_amount' => (float) env('PAY_LARGE_INPUT_AMOUNT', 10000000),
        // Nominal "bulat" kelipatan nilai ini dan >= large_input_amount ditandai (pola umum pengisian fiktif).
        'round_input_unit' => (float) env('PAY_ROUND_INPUT_UNIT', 1000000),

        // Batas lembur menurut PP 35/2021: 4 jam/hari dan 18 jam/minggu.
        'overtime_max_hours_day' => (float) env('PAY_OT_MAX_DAY', 4),
        'overtime_max_hours_week' => (float) env('PAY_OT_MAX_WEEK', 18),

        // Total potongan (cicilan kasbon dst) maksimum % dari gaji sebelum potongan (PP 36/2021 Pasal 65: 50%).
        'deduction_cap_pct' => (float) env('PAY_DEDUCTION_CAP_PCT', 50),

        // Rekonsiliasi hasil bank: selisih nominal > nilai ini (rupiah) dianggap tidak cocok.
        'reconcile_tolerance' => (float) env('PAY_RECONCILE_TOLERANCE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aturan perhitungan operasional
    |--------------------------------------------------------------------------
    */
    'payroll' => [
        // Pembagi upah per jam untuk lembur (Kepmenakertrans 102/2004 & PP 35/2021: 1/173 upah sebulan).
        'overtime_divisor' => (int) env('PAY_OT_DIVISOR', 173),

        // Dasar potongan ketidakhadiran: "scheduled" = hari kerja efektif bulan itu (dari SDM); atau angka tetap (mis. 21, 25, 30).
        'absence_divisor' => env('PAY_ABSENCE_DIVISOR', 'scheduled'),

        // Iuran yang dibayar perusahaan dan MASUK penghasilan bruto untuk dasar TER (PMK 168/2023).
        // Pilihan: kes (BPJS Kesehatan), jkk, jkm. Konfirmasikan perlakuan BPJS Kesehatan dengan konsultan pajak perusahaan.
        'taxable_employer_premiums' => array_filter(explode(',', env('PAY_TAXABLE_EMPLOYER_PREMIUMS', 'kes,jkk,jkm'))),

        // Pembulatan nominal ke rupiah terdekat pada pajak & iuran (true) atau biarkan 2 desimal (false).
        'round_to_rupiah' => (bool) env('PAY_ROUND_TO_RUPIAH', true),
    ],

    'audit' => [
        // Alamat email (pisahkan koma) penerima "jangkar rantai audit" harian: hash terbaru dikirim ke luar sistem
        // sehingga rantai yang ditulis ulang di database tetap ketahuan.
        'anchor_emails' => array_filter(array_map('trim', explode(',', (string) env('PAY_AUDIT_ANCHOR_EMAILS', '')))),
    ],

    'payslip' => [
        // Kata sandi PDF slip gaji yang dikirim lewat email: "dob" = tanggal lahir DDMMYYYY (bila ada), lalu NIP.
        'encrypt_pdf' => (bool) env('PAY_PAYSLIP_ENCRYPT', true),
    ],

];
