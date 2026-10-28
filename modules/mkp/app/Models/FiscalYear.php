<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['name', 'start_date', 'end_date', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class);
    }
}
