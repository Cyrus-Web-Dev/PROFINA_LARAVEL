<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'price_level_id', 'price', 'min_qty'];
}
