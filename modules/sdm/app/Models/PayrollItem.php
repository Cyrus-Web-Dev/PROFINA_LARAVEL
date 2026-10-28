<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'basic_salary', 'allowances', 'other_deductions',
        'overtime_amount', 'bpjs_kesehatan_employee', 'bpjs_kesehatan_company',
        'bpjs_jht_employee', 'bpjs_jht_company', 'bpjs_jkk_company', 'bpjs_jkm_company',
        'bpjs_jp_employee', 'bpjs_jp_company', 'pph21', 'gross_salary', 'total_deductions',
        'net_salary', 'status', 'paid_at', 'payment_method', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'allowances' => 'array',
            'other_deductions' => 'array',
            'basic_salary' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'bpjs_kesehatan_employee' => 'decimal:2',
            'bpjs_kesehatan_company' => 'decimal:2',
            'bpjs_jht_employee' => 'decimal:2',
            'bpjs_jht_company' => 'decimal:2',
            'bpjs_jkk_company' => 'decimal:2',
            'bpjs_jkm_company' => 'decimal:2',
            'bpjs_jp_employee' => 'decimal:2',
            'bpjs_jp_company' => 'decimal:2',
            'pph21' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
