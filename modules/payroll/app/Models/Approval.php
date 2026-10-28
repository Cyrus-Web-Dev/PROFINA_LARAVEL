<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $guarded = [];

    protected $casts = ['acted_at' => 'datetime'];
}
