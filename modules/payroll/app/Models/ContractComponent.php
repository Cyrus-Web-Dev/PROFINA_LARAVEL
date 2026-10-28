<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractComponent extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayComponent::class, 'pay_component_id');
    }
}
