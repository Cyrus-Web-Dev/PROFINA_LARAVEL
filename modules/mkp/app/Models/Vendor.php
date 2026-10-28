<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = ['code', 'name', 'address', 'phone', 'email', 'npwp', 'is_active'];

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function outstandingBalance(): float
    {
        return $this->bills()->whereIn('status', ['unpaid', 'partial'])->get()
            ->sum(fn ($b) => $b->total - $b->paid_amount);
    }
}
