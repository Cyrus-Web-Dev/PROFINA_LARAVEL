<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    protected $fillable = ['number', 'sales_order_id', 'customer_id', 'status', 'delivery_date', 'driver', 'vehicle_no', 'ship_to', 'received_by', 'received_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'received_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }
}
