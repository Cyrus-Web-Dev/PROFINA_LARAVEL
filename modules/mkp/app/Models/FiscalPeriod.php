<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalPeriod extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['fiscal_year_id', 'month', 'start_date', 'end_date', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
