<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobVacancy extends Model
{
    protected $fillable = [
        'title', 'slug', 'department_id', 'position_id', 'employment_type',
        'description', 'requirements', 'slots', 'status', 'posted_by', 'opens_at', 'closes_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'date',
            'closes_at' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function applicants(): HasMany
    {
        return $this->hasMany(JobApplicant::class);
    }
}
