<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['customer_id', 'product_id', 'price', 'min_qty'];
}
