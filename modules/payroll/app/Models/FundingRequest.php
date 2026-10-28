<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'breakdown' => 'array', 'journal_refs' => 'array', 'amount_net_total' => 'float', 'amount_disbursable' => 'float',
        'amount_approved' => 'float', 'disbursed_amount' => 'float', 'decided_at' => 'datetime', 'disbursed_at' => 'datetime', 'delivered_at' => 'datetime',
    ];

    public const STATUS_LABELS = ['submitted' => 'Menunggu Keuangan', 'approved' => 'Disetujui Keuangan', 'rejected' => 'Ditolak', 'revision' => 'Perlu revisi', 'disbursed' => 'Dana dicairkan', 'confirmed' => 'Terkonfirmasi', 'cancelled' => 'Dibatalkan'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
