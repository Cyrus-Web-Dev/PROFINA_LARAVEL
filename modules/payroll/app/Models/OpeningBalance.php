<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalance extends Model
{
    protected $guarded = [];

    protected $casts = ['gross_ytd' => 'float', 'pension_ytd' => 'float', 'pph21_ytd' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
