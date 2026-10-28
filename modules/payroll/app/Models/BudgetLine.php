<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
