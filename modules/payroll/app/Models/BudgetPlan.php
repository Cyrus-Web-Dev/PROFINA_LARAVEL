<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class BudgetPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['total_amount' => 'float', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'activated_at' => 'datetime', 'pushed_to_finance_at' => 'datetime'];

    public const CATEGORIES = ['basic' => 'Gaji pokok', 'allowance' => 'Tunjangan', 'overtime' => 'Lembur', 'bonus' => 'Bonus', 'thr' => 'THR', 'bpjs_company' => 'BPJS perusahaan', 'other' => 'Lainnya'];

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->orderBy('step');
    }
}
