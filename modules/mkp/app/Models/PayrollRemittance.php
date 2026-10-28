<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRemittance extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float', 'due_date' => 'date', 'report_due_date' => 'date', 'paid_at' => 'datetime'];
}
