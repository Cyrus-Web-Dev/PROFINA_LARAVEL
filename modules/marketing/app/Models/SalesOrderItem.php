<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['sales_order_id', 'product_id', 'description', 'unit', 'qty', 'delivered_qty', 'unit_price', 'discount_pct', 'line_total', 'sort'];
}
