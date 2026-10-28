<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remittance extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'due_date' => 'date', 'report_due_date' => 'date', 'paid_at' => 'datetime', 'reminder_sent_at' => 'datetime'];

    public const TYPE_LABELS = ['pph21' => 'PPh Pasal 21', 'bpjs_kes' => 'BPJS Kesehatan', 'bpjs_tk' => 'BPJS Ketenagakerjaan'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
