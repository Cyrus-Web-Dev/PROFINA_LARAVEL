<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Ringkasan untuk dashboard pemantau Portal PROFINA. */
class ProfinaMetrics
{
    public function __invoke(): array
    {
        return [
            'karyawan_aktif' => DB::table('employees')->where('employment_status', 'active')->whereNull('deleted_at')->count(),
            'cuti_menunggu' => DB::table('leaves')->where('status', 'pending')->count(),
            'akun_login' => DB::table('users')->where('status', 'active')->count(),
        ];
    }
}
