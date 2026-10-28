<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipMirror extends Model
{
    protected $guarded = [];

    protected $casts = ['gross' => 'float', 'net' => 'float', 'published_at' => 'datetime'];
}
