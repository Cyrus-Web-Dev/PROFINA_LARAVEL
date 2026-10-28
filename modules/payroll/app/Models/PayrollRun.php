<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_start' => 'date', 'period_end' => 'date', 'pay_date' => 'date', 'thr_holiday_date' => 'date',
            'calculated_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'approved_at' => 'datetime',
            'second_approved_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
            'locked_at' => 'datetime', 'paid_at' => 'datetime', 'closed_at' => 'datetime',
            'requires_dual_approval' => 'boolean', 'totals' => 'array', 'budget_check' => 'array', 'calc_meta' => 'array',
            'total_gross' => 'float', 'total_net' => 'float', 'total_pph21' => 'float', 'total_employer_bpjs' => 'float',
            'total_employee_bpjs' => 'float', 'total_other_deductions' => 'float', 'total_disbursable' => 'float', 'total_employer_cost' => 'float',
        ];
    }

    public const STATUS_LABELS = [
        'draft' => 'Draf', 'calculated' => 'Sudah dihitung', 'in_review' => 'Menunggu verifikasi', 'verified' => 'Menunggu persetujuan',
        'approved' => 'Disetujui & terkunci', 'funding' => 'Menunggu dana Keuangan', 'funded' => 'Dana siap', 'disbursing' => 'Transfer berjalan',
        'paid' => 'Terbayar', 'closed' => 'Ditutup', 'rejected' => 'Dikembalikan', 'cancelled' => 'Dibatalkan',
    ];

    public const TYPE_LABELS = ['regular' => 'Gaji bulanan', 'thr' => 'THR', 'bonus' => 'Bonus', 'final' => 'Final settlement', 'correction' => 'Koreksi'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(PayrollFinding::class);
    }

    public function funding(): HasOne
    {
        return $this->hasOne(FundingRequest::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(DisbursementBatch::class);
    }

    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->orderBy('step');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function periodLabel(): string
    {
        return \Carbon\Carbon::create($this->period_year, $this->period_month, 1)->locale('id')->translatedFormat('F Y');
    }
}
