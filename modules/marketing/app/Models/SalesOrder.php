<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = ['number', 'quotation_id', 'customer_id', 'sales_user_id', 'status', 'order_date', 'target_date', 'subtotal', 'tax_pct', 'tax_amount', 'total', 'ship_to', 'notes'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'target_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class)->orderBy('sort');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('scope.all') ? $q : $q->where('sales_orders.sales_user_id', $user->id);
    }
}
