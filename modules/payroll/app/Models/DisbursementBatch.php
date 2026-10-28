<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementBatch extends Model
{
    protected $guarded = [];

    protected $casts = ['total_amount' => 'float', 'reconciliation' => 'array', 'generated_at' => 'datetime', 'reconciled_at' => 'datetime'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
