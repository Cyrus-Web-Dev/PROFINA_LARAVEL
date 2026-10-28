<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ProfinaMetrics
{
    public function __invoke(): array
    {
        return [
            'penawaran_terkirim' => DB::table('quotations')->where('status', 'sent')->count(),
            'so_berjalan' => DB::table('sales_orders')->whereIn('status', ['confirmed', 'in_production', 'ready', 'partial'])->count(),
            'komplain_terbuka' => DB::table('complaints')->whereIn('status', ['open', 'in_progress'])->count(),
        ];
    }
}
