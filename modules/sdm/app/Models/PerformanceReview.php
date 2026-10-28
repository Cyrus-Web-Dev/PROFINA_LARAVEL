<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    protected $fillable = [
        'cycle_id', 'employee_id', 'reviewer_id', 'kpi_items', 'final_score', 'rating',
        'strengths', 'improvements', 'employee_comment', 'status', 'submitted_at', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'kpi_items' => 'array',
            'final_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
