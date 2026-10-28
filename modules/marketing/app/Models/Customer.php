<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'company', 'price_level_id', 'email', 'phone', 'address', 'city', 'npwp', 'payment_terms_days', 'credit_limit', 'owner_id', 'status', 'notes'];

    public function level(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class, 'price_level_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function specialPrices(): HasMany
    {
        return $this->hasMany(CustomerPrice::class);
    }

    /** Tanpa izin scope.all: hanya pelanggan milik sendiri. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('scope.all') ? $q : $q->where($q->getModel()->getTable().'.owner_id', $user->id);
    }
}
