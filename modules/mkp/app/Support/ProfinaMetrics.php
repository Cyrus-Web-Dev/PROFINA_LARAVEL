<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ProfinaMetrics
{
    public function __invoke(): array
    {
        return [
            'jurnal_draft' => DB::table('journals')->where('status', 'draft')->count(),
            'jurnal_menunggu_persetujuan' => DB::table('journals')->where('approval_status', 'pending')->count(),
            'akun_login' => DB::table('users')->where('status', 'active')->count(),
        ];
    }
}
