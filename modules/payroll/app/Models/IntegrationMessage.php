<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];
}
