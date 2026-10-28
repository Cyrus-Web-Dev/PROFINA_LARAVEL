<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollInput extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'hours' => 'float', 'work_date' => 'date', 'attendance_check' => 'array', 'approved_at' => 'datetime'];

    public const TYPE_LABELS = ['overtime' => 'Lembur', 'bonus' => 'Bonus', 'allowance' => 'Tunjangan insidentil', 'reimbursement' => 'Reimburse', 'deduction' => 'Potongan', 'correction' => 'Koreksi (+)'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
