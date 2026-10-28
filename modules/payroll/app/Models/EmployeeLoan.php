<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLoan extends Model
{
    protected $guarded = [];

    protected $casts = ['principal' => 'float', 'installment_amount' => 'float', 'remaining' => 'float', 'approved_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
