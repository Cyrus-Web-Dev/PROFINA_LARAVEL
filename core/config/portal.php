<?php

/*
|--------------------------------------------------------------------------
| PROFINA Core (Portal) - katalog modul & pengaturan keamanan
|--------------------------------------------------------------------------
| "roles" di tiap modul HARUS sama dengan nama role yang dibuat seeder modul tersebut
| (RolePermissionSeeder di modules/<kode>). Label hanya untuk tampilan.
*/
return [

    // Kunci induk. Kunci tiap modul diturunkan darinya (php artisan profina:provision). JANGAN dibagikan ke modul.
    'master_key' => env('PROFINA_MASTER_KEY'),

    // Umur token masuk (detik) dan umur maksimal sesi SSO modul (jam) sebelum wajib masuk ulang lewat Portal.
    'token_ttl' => (int) env('PROFINA_TOKEN_TTL', 60),
    'sso_session_hours' => (int) env('PROFINA_SSO_SESSION_HOURS', 12),

    'registration_enabled' => (bool) env('PROFINA_REGISTRATION_ENABLED', true),

    'security' => [
        'csp' => env('PROFINA_CSP', 'report-only'),
        'force_https' => (bool) env('PROFINA_FORCE_HTTPS', false),
        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
        ],
    ],

    'admin' => [
        'name' => env('PROFINA_ADMIN_NAME', 'Administrator PROFINA'),
        'email' => env('PROFINA_ADMIN_EMAIL'),
        'password' => env('PROFINA_ADMIN_PASSWORD'),
    ],

    // Peran di Portal itu sendiri (modul "Inti": pengelola & pemantau semua modul).
    'core_roles' => [
        'super-admin' => 'Super Admin (kelola pengguna, penugasan, pantau, audit)',
        'admin-portal' => 'Admin Portal (kelola pengguna & penugasan, pantau)',
        'pemantau' => 'Pemantau (lihat dashboard & audit saja)',
    ],

    'modules' => [
        'sdm' => [
            'name' => 'SDM / HRIS',
            'description' => 'Karyawan, absensi, cuti, kinerja, rekrutmen, pelatihan, dokumen.',
            'url' => rtrim((string) env('PROFINA_URL_SDM', 'http://sdm.localhost:8002'), '/'),
            'internal_url' => rtrim((string) env('PROFINA_INTERNAL_URL_SDM', 'http://127.0.0.1:8002'), '/'),
            'color' => '#0f766e',
            'roles' => [
                'super-admin' => 'Super Admin SDM',
                'hr-admin' => 'Admin HR',
                'manajer' => 'Manajer (tim sendiri)',
                'karyawan' => 'Karyawan (layanan mandiri)',
            ],
        ],
        'payroll' => [
            'name' => 'Payroll & Budgeting',
            'description' => 'Kontrak kompensasi, penggajian, pajak/BPJS, pembayaran, anggaran SDM.',
            'url' => rtrim((string) env('PROFINA_URL_PAYROLL', 'http://payroll.localhost:8003'), '/'),
            'internal_url' => rtrim((string) env('PROFINA_INTERNAL_URL_PAYROLL', 'http://127.0.0.1:8003'), '/'),
            'color' => '#7c3aed',
            'roles' => [
                'super-admin' => 'Super Admin Payroll',
                'payroll-admin' => 'Payroll Admin (pembuat)',
                'payroll-manager' => 'Payroll Manager (pemeriksa)',
                'finance-approver' => 'Finance Approver (penyetuju)',
                'direksi' => 'Direksi',
                'hr-partner' => 'HR Partner',
                'auditor' => 'Auditor (baca saja)',
                'karyawan' => 'Karyawan (slip sendiri)',
            ],
        ],
        'mkp' => [
            'name' => 'MKP - Keuangan & Akuntansi',
            'description' => 'Buku besar, jurnal, piutang/utang, kas & bank, aset tetap, anggaran, laporan.',
            'url' => rtrim((string) env('PROFINA_URL_MKP', 'http://mkp.localhost:8001'), '/'),
            'internal_url' => rtrim((string) env('PROFINA_INTERNAL_URL_MKP', 'http://127.0.0.1:8001'), '/'),
            'color' => '#b45309',
            'roles' => [
                'admin' => 'Manager (semua hak)',
                'staff' => 'Staff (hak per orang)',
                'viewer' => 'Viewer (baca saja)',
            ],
            // Hak tambahan per orang untuk role "staff" (dicentang Admin Portal saat memberi penugasan).
            'permissions' => [
                'coa.manage' => 'Kelola bagan akun', 'journal.create' => 'Buat jurnal', 'journal.post' => 'Posting jurnal',
                'journal.void' => 'Batalkan jurnal', 'journal.approve' => 'Setujui jurnal', 'ar.manage' => 'Piutang',
                'ap.manage' => 'Utang', 'cashbank.manage' => 'Kas & bank', 'fixed-asset.manage' => 'Aset tetap',
                'budget.manage' => 'Anggaran', 'bank-reconciliation.manage' => 'Rekonsiliasi bank',
                'procurement.decide' => 'Putuskan dana pengadaan', 'procurement.disburse' => 'Cairkan dana pengadaan',
                'payroll.decide' => 'Putuskan dana payroll', 'payroll.disburse' => 'Cairkan dana payroll', 'reports.view' => 'Lihat laporan',
            ],
        ],
        'marketing' => [
            'name' => 'Marketing & Sales',
            'description' => 'Pelanggan, katalog & harga per level, penawaran, sales order, surat jalan, komplain.',
            'url' => rtrim((string) env('PROFINA_URL_MARKETING', 'http://marketing.localhost:8004'), '/'),
            'internal_url' => rtrim((string) env('PROFINA_INTERNAL_URL_MARKETING', 'http://127.0.0.1:8004'), '/'),
            'color' => '#be123c',
            'roles' => [
                'marketing-manager' => 'Marketing Manager (semua hak, termasuk HPP)',
                'sales-admin' => 'Sales Admin (kelola data & dokumen)',
                'sales' => 'Sales (penawaran & pelanggan sendiri)',
                'viewer' => 'Viewer (baca saja)',
            ],
        ],
    ],
];
