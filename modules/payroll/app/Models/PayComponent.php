<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayComponent extends Model
{
    protected $guarded = [];

    protected $casts = ['taxable' => 'boolean', 'bpjs_base' => 'boolean', 'prorated' => 'boolean', 'is_system' => 'boolean', 'is_active' => 'boolean'];
}
