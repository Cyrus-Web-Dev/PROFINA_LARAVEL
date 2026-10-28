<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollEvent extends Model
{
    protected $guarded = [];

    protected $casts = ['payload' => 'array'];
}
