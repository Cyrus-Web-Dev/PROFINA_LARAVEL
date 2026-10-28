<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class ProfinaMetrics
{
    public function __invoke(): array
    {
        return [
            'putaran_draft_s/d_verifikasi' => DB::table('payroll_runs')->whereIn('status', ['draft', 'calculated', 'in_review', 'verified'])->count(),
            'putaran_menunggu_cair' => DB::table('payroll_runs')->whereIn('status', ['approved', 'funding', 'funded', 'disbursing'])->count(),
            'temuan_terbuka' => DB::table('payroll_findings')->where('status', 'open')->count(),
        ];
    }
}
