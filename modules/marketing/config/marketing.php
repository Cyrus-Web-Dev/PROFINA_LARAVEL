<?php

return [
    // Diskon baris maksimum (%) yang boleh diberikan TANPA izin prices.override.
    'max_discount_pct' => (float) env('MKT_MAX_DISCOUNT_PCT', 5),
    'tax_pct' => (float) env('MKT_TAX_PCT', 11),
    'quotation_valid_days' => (int) env('MKT_QUOTATION_VALID_DAYS', 14),

    'company' => [
        'name' => env('MKT_COMPANY_NAME', 'PT Contoh Produksi'),
        'address' => env('MKT_COMPANY_ADDRESS', 'Alamat perusahaan'),
        'phone' => env('MKT_COMPANY_PHONE', ''),
        'email' => env('MKT_COMPANY_EMAIL', ''),
        'bank' => env('MKT_COMPANY_BANK', ''),
    ],

    'security' => [
        'csp' => env('MKT_CSP', 'report-only'),
        'force_https' => (bool) env('MKT_FORCE_HTTPS', false),
    ],
];
