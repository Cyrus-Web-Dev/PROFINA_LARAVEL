<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['code', 'name', 'address', 'phone', 'email', 'npwp', 'credit_limit', 'is_active'];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function outstandingBalance(): float
    {
        return $this->invoices()->whereIn('status', ['unpaid', 'partial'])->get()
            ->sum(fn ($i) => $i->total - $i->paid_amount);
    }
}
