<?php

/*
| Integrasi dengan PROCURA (Sistem Pengadaan) lewat REST API.
| Token & rahasia webhook dibuat oleh Manager PROCURA di menu "Integrasi API" (klien bertipe "Keuangan").
*/
return [
    // Contoh: http://127.0.0.1:8000/api/v1
    'url' => rtrim((string) env('PROCURA_URL', ''), '/'),
    'token' => env('PROCURA_TOKEN'),
    'webhook_secret' => env('PROCURA_WEBHOOK_SECRET'),
    'timeout' => (int) env('PROCURA_TIMEOUT', 10),

    // Pengajuan yang belum disinkronkan lebih dari N menit ikut diperbarui oleh `php artisan procura:sync`.
    'sync_stale_minutes' => (int) env('PROCURA_SYNC_STALE_MINUTES', 10),

    // Keputusan "setujui" dengan nominal >= ini wajib dilakukan pemegang hak journal.approve (Manager).
    'approval_threshold' => (float) env('PROCURA_APPROVAL_THRESHOLD', env('MKP_APPROVAL_THRESHOLD', 50000000)),

    // Akun "Uang Muka Pengadaan" (aset lancar). Dibuat otomatis bila belum ada di Daftar Akun.
    'advance_account' => ['code' => env('PROCURA_ADVANCE_ACCOUNT', '1-1700'), 'name' => 'Uang Muka Pengadaan'],

    // Saran akun debit saat menerima LPJ, menurut kategori barang/jasa. Akuntan tetap bisa memilih akun lain.
    'category_accounts' => [
        'elektronik' => '1-2400',      // Peralatan Kantor
        'non_elektronik' => '1-2400',
        'perabotan' => '1-2400',
        'kendaraan' => '1-2300',       // Kendaraan
        'gedung' => '1-2200',          // Bangunan
        'jasa' => '5-1900',            // Beban Lain-lain
        'habis_pakai' => '5-1400',     // Beban Perlengkapan
    ],
    // Kategori yang sebaiknya juga didaftarkan di modul Aset Tetap (agar disusutkan).
    'capital_categories' => ['elektronik', 'non_elektronik', 'perabotan', 'kendaraan', 'gedung'],

    'categories' => [
        'elektronik' => 'Elektronik', 'non_elektronik' => 'Non Elektronik', 'perabotan' => 'Perabotan', 'kendaraan' => 'Kendaraan',
        'gedung' => 'Gedung & Renovasi', 'jasa' => 'Jasa', 'habis_pakai' => 'Habis Pakai',
    ],

    // Status lokal (mengikuti PROCURA) => [label, warna badge]
    'status' => [
        'submitted' => ['Menunggu keputusan', 'amber'],
        'approved' => ['Disetujui, belum cair', 'teal'],
        'revision' => ['Diminta revisi', 'violet'],
        'rejected' => ['Ditolak', 'rose'],
        'disbursed' => ['Dana cair', 'sky'],
        'settled' => ['LPJ diserahkan', 'indigo'],
        'closed' => ['LPJ dijurnal', 'emerald'],
        'cancelled' => ['Dibatalkan Pengadaan', 'slate'],
    ],
];
