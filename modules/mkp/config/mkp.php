<?php

return [

    // Jurnal manual dengan total >= nilai ini WAJIB disetujui Manager sebelum bisa diposting.
    'approval_threshold' => (float) env('MKP_APPROVAL_THRESHOLD', 50000000),

    // Berapa hari sebelum jatuh tempo invoice/bill mulai muncul sebagai pengingat.
    'due_reminder_days' => (int) env('MKP_DUE_REMINDER_DAYS', 7),

    // "open"  : siapa pun boleh mendaftar, tetapi akun baru berstatus MENUNGGU PERSETUJUAN Manager
    //           dan belum bisa membuka data apa pun.
    // "closed": pendaftaran ditutup total (kecuali belum ada user sama sekali).
    // Pendaftar PERTAMA (saat belum ada user) selalu otomatis menjadi Manager.
    'registration' => env('MKP_REGISTRATION', 'open'),

    // Admin awal yang dibuat oleh `php artisan migrate --seed` (opsional).
    'admin' => [
        'name' => env('MKP_ADMIN_NAME', 'Administrator'),
        'email' => env('MKP_ADMIN_EMAIL'),
        'password' => env('MKP_ADMIN_PASSWORD'),
    ],

    // Posisi kerja + hak akses AWAL yang otomatis tercentang saat Manager menyetujui staf.
    // Manager tetap bisa menambah/mengurangi centangnya per orang. (reports.view selalu ada.)
    'positions' => [
        'accounting' => ['label' => 'Accounting', 'permissions' => ['coa.manage', 'journal.create', 'journal.post', 'fixed-asset.manage', 'budget.manage']],
        'finance' => ['label' => 'Finance', 'permissions' => ['ar.manage', 'ap.manage', 'cashbank.manage', 'bank-reconciliation.manage', 'procurement.decide', 'procurement.disburse', 'payroll.decide', 'payroll.disburse']],
        'pajak' => ['label' => 'Pajak', 'permissions' => ['journal.create']],
        'payroll' => ['label' => 'Payroll', 'permissions' => ['journal.create']],
        'lainnya' => ['label' => 'Lainnya', 'permissions' => []],
    ],

    // Hak akses yang bisa diberikan per orang (Manager selalu punya semuanya).
    'permission_labels' => [
        'coa.manage' => 'Kelola Daftar Akun (COA)',
        'journal.create' => 'Input & ubah jurnal',
        'journal.post' => 'Posting jurnal',
        'journal.void' => 'Void jurnal',
        'journal.approve' => 'Setujui jurnal bernilai besar',
        'ar.manage' => 'Piutang, pelanggan & invoice',
        'ap.manage' => 'Utang, vendor & bill',
        'cashbank.manage' => 'Kas & bank (transfer)',
        'fixed-asset.manage' => 'Aset tetap & depresiasi',
        'budget.manage' => 'Anggaran',
        'bank-reconciliation.manage' => 'Rekonsiliasi bank',
        'procurement.decide' => 'Putuskan pengajuan dana dari Pengadaan',
        'procurement.disburse' => 'Cairkan dana & terima LPJ Pengadaan',
        'payroll.decide' => 'Putuskan pengajuan dana gaji dari Payroll (jurnal akrual)',
        'payroll.disburse' => 'Cairkan gaji & setor pajak/BPJS dari Payroll',
    ],

    'backup' => [
        // Jumlah file backup terbaru yang disimpan; yang lebih lama dihapus otomatis.
        'keep' => (int) env('MKP_BACKUP_KEEP', 10),
        // Opsional: path lengkap mysqldump / pg_dump bila tidak ditemukan otomatis.
        'mysqldump_path' => env('MKP_MYSQLDUMP_PATH'),
        'pg_dump_path' => env('MKP_PG_DUMP_PATH'),
    ],

];
