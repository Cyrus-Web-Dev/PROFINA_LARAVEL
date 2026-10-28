<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollFunding extends Model
{
    protected $guarded = [];

    protected $casts = [
        'breakdown' => 'array', 'bank_batches' => 'array', 'snapshot' => 'array', 'pay_date' => 'date', 'decided_at' => 'datetime', 'disbursed_at' => 'datetime', 'synced_at' => 'datetime',
        'amount_net_total' => 'float', 'amount_disbursable' => 'float', 'approved_amount' => 'float', 'disbursed_amount' => 'float',
    ];
}
