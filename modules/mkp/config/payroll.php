<?php

/*
| Integrasi dengan PAYBRIDGE (Payroll & Budgeting) lewat REST API + webhook.
| Token & rahasia webhook dibuat di PAYBRIDGE > Integrasi API (klien bertipe "Keuangan", scope funding:*, remittance:*, budget:read).
*/
return [
    'url' => rtrim((string) env('PAYROLL_URL', ''), '/'),                 // contoh: http://127.0.0.1:8003/api/v1
    'token' => env('PAYROLL_TOKEN'),
    'webhook_secret' => env('PAYROLL_WEBHOOK_SECRET'),
    'timeout' => (int) env('PAYROLL_TIMEOUT', 10),
    'sync_stale_minutes' => (int) env('PAYROLL_SYNC_STALE_MINUTES', 10),

    // Menyetujui pengajuan dana gaji >= nilai ini wajib pemegang hak journal.approve (Manager).
    'approval_threshold' => (float) env('PAYROLL_APPROVAL_THRESHOLD', env('MKP_APPROVAL_THRESHOLD', 50000000)),

    // true = anggaran SDM aktif dari PAYBRIDGE langsung ditulis ke modul Anggaran (per akun per bulan).
    'auto_apply_budget' => (bool) env('PAYROLL_AUTO_APPLY_BUDGET', true),

    /*
    | Kunci semantik dari PAYBRIDGE -> akun buku besar MKP. Akun yang belum ada dibuat otomatis (aman diulang).
    | [kode, nama, tipe, kategori, saldo normal, kode induk]
    */
    'accounts' => [
        'salary_expense' => ['5-1100', 'Beban Gaji', 'beban', null, 'debit', null],
        'allowance_expense' => ['5-1105', 'Beban Tunjangan', 'beban', null, 'debit', null],
        'overtime_expense' => ['5-1110', 'Beban Lembur', 'beban', null, 'debit', null],
        'bonus_expense' => ['5-1115', 'Beban Bonus', 'beban', null, 'debit', null],
        'thr_expense' => ['5-1116', 'Beban THR', 'beban', null, 'debit', null],
        'reimbursement_expense' => ['5-1117', 'Beban Reimburse Karyawan', 'beban', null, 'debit', null],
        'bpjs_kes_company_expense' => ['5-1120', 'Beban BPJS Kesehatan (Perusahaan)', 'beban', null, 'debit', null],
        'bpjs_tk_company_expense' => ['5-1125', 'Beban BPJS Ketenagakerjaan (Perusahaan)', 'beban', null, 'debit', null],
        'net_pay_liability' => ['2-1200', 'Utang Gaji', 'kewajiban', 'kewajiban_lancar', 'kredit', '2-1000'],
        'pph21_liability' => ['2-1310', 'Utang PPh Pasal 21', 'kewajiban', 'kewajiban_lancar', 'kredit', '2-1000'],
        'bpjs_kes_liability' => ['2-1320', 'Utang BPJS Kesehatan', 'kewajiban', 'kewajiban_lancar', 'kredit', '2-1000'],
        'bpjs_tk_liability' => ['2-1330', 'Utang BPJS Ketenagakerjaan', 'kewajiban', 'kewajiban_lancar', 'kredit', '2-1000'],
        'other_deduction_payable' => ['2-1340', 'Utang Potongan Karyawan Lainnya', 'kewajiban', 'kewajiban_lancar', 'kredit', '2-1000'],
        'employee_receivable' => ['1-1800', 'Piutang Karyawan', 'aset', 'aset_lancar', 'debit', '1-1000'],
    ],

    // Kunci anggaran SDM -> kunci akun di atas (BPJS perusahaan dari anggaran digabung ke satu akun).
    'budget_map' => ['bpjs_company_expense' => 'bpjs_kes_company_expense'],

    // Setoran -> akun utang yang didebit saat dibayar.
    'remittance_accounts' => ['pph21' => 'pph21_liability', 'bpjs_kes' => 'bpjs_kes_liability', 'bpjs_tk' => 'bpjs_tk_liability'],
    'remittance_labels' => ['pph21' => 'PPh Pasal 21', 'bpjs_kes' => 'BPJS Kesehatan', 'bpjs_tk' => 'BPJS Ketenagakerjaan'],
];
