<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CompensationContract extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_salary' => 'float', 'effective_date' => 'date', 'end_date' => 'date', 'superseded_on' => 'date',
            'bpjs_kes_enrolled' => 'boolean', 'bpjs_tk_enrolled' => 'boolean', 'thr_eligible' => 'boolean', 'overtime_eligible' => 'boolean',
            'verified_at' => 'datetime', 'approved_at' => 'datetime', 'second_approved_at' => 'datetime',
            'rejected_at' => 'datetime', 'activated_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(ContractComponent::class, 'contract_id');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->orderBy('step');
    }

    /** Hash isi kontrak: berubah bila ada yang mengubah angka/komponen di database tanpa lewat alur resmi. */
    public static function hashTerms(array $t, iterable $components): string
    {
        $comp = [];
        foreach ($components as $c) {
            $comp[(int) $c['pay_component_id']] = number_format((float) $c['amount'], 2, '.', '');
        }
        ksort($comp);
        $canon = [
            'employee_id' => (int) $t['employee_id'],
            'base_salary' => number_format((float) $t['base_salary'], 2, '.', ''),
            'effective_date' => (string) $t['effective_date'],
            'end_date' => $t['end_date'] ?: null,
            'grade_id' => $t['grade_id'] ?: null,
            'contract_type' => $t['contract_type'],
            'jkk_class' => (int) $t['jkk_class'],
            'bpjs_kes' => (bool) $t['bpjs_kes_enrolled'], 'bpjs_tk' => (bool) $t['bpjs_tk_enrolled'],
            'tax_scheme' => $t['tax_scheme'], 'thr' => (bool) $t['thr_eligible'], 'ot' => (bool) $t['overtime_eligible'],
            'components' => $comp,
        ];

        return hash('sha256', json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function currentHash(): string
    {
        $this->loadMissing('components');

        return self::hashTerms([
            'employee_id' => $this->employee_id, 'base_salary' => $this->base_salary,
            'effective_date' => $this->effective_date->toDateString(), 'end_date' => $this->end_date?->toDateString(),
            'grade_id' => $this->grade_id, 'contract_type' => $this->contract_type, 'jkk_class' => $this->jkk_class,
            'bpjs_kes_enrolled' => $this->bpjs_kes_enrolled, 'bpjs_tk_enrolled' => $this->bpjs_tk_enrolled,
            'tax_scheme' => $this->tax_scheme, 'thr_eligible' => $this->thr_eligible, 'overtime_eligible' => $this->overtime_eligible,
        ], $this->components->map(fn ($c) => ['pay_component_id' => $c->pay_component_id, 'amount' => $c->amount]));
    }

    public function intact(): bool
    {
        return hash_equals($this->terms_hash, $this->currentHash());
    }
}
