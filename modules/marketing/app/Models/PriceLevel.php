<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceLevel extends Model
{
    protected $fillable = ['code', 'name', 'discount_pct', 'sort'];
}
