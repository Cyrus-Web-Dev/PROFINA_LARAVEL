<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSnapshot extends Model
{
    protected $guarded = [];

    protected $casts = ['details' => 'array', 'fetched_at' => 'datetime'];
}
