<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNoteItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_note_id', 'sales_order_item_id', 'qty'];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
