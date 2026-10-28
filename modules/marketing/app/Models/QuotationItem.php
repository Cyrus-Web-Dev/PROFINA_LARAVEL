<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['quotation_id', 'product_id', 'description', 'unit', 'qty', 'unit_price', 'discount_pct', 'line_total', 'sort'];
}
