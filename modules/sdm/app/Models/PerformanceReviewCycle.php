<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReviewCycle extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'status', 'created_by'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }
}
