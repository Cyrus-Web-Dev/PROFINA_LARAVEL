<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $guarded = [];

    protected $hidden = ['bank_account_hash'];

    protected function casts(): array
    {
        return [
            'employee_snapshot' => 'array', 'lines' => 'array', 'tax_detail' => 'array', 'is_held' => 'boolean',
            'paid_at' => 'datetime', 'held_at' => 'datetime', 'payslip_published_at' => 'datetime', 'payslip_emailed_at' => 'datetime',
            'basic_salary' => 'float', 'fixed_allowances' => 'float', 'variable_earnings' => 'float', 'gross_earnings' => 'float',
            'bpjs_kes_employee' => 'float', 'bpjs_kes_company' => 'float', 'bpjs_jht_employee' => 'float', 'bpjs_jht_company' => 'float',
            'bpjs_jkk_company' => 'float', 'bpjs_jkm_company' => 'float', 'bpjs_jp_employee' => 'float', 'bpjs_jp_company' => 'float',
            'tax_gross' => 'float', 'pph21' => 'float', 'ter_rate' => 'float', 'loan_deduction' => 'float', 'other_deductions' => 'float',
            'total_employee_deductions' => 'float', 'net_pay' => 'float', 'employer_cost' => 'float',
            'days_present' => 'float', 'days_absent' => 'float', 'days_unpaid_leave' => 'float', 'overtime_hours' => 'float', 'proration_factor' => 'float',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(CompensationContract::class, 'contract_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Hash isi item (angka & baris komponen & rekening). */
    public function computeHash(): string
    {
        $canon = [
            'run' => $this->payroll_run_id, 'emp' => $this->employee_id, 'contract' => $this->contract_id, 'terms' => $this->contract_terms_hash,
            'gross' => number_format((float) $this->gross_earnings, 2, '.', ''), 'net' => number_format((float) $this->net_pay, 2, '.', ''),
            'pph' => number_format((float) $this->pph21, 2, '.', ''), 'bank' => $this->bank_account_hash,
            'lines' => collect($this->lines)->map(fn ($l) => [$l['code'], number_format((float) $l['amount'], 2, '.', '')])->all(),
            'bpjs' => [(float) $this->bpjs_kes_employee, (float) $this->bpjs_kes_company, (float) $this->bpjs_jht_employee, (float) $this->bpjs_jht_company,
                (float) $this->bpjs_jkk_company, (float) $this->bpjs_jkm_company, (float) $this->bpjs_jp_employee, (float) $this->bpjs_jp_company],
        ];

        return hash('sha256', json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
