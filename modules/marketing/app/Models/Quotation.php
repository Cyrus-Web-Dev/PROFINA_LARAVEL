<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends Model
{
    protected $fillable = ['number', 'customer_id', 'sales_user_id', 'status', 'quote_date', 'valid_until', 'subtotal', 'tax_pct', 'tax_amount', 'total', 'terms', 'notes', 'sent_at', 'decided_by', 'decided_at', 'reject_reason'];

    protected function casts(): array
    {
        return ['quote_date' => 'date', 'valid_until' => 'date', 'sent_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort');
    }

    public function order(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('scope.all') ? $q : $q->where('quotations.sales_user_id', $user->id);
    }
}
