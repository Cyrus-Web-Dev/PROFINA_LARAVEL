<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = ['name', 'default_quota_per_year', 'is_paid', 'requires_attachment'];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
        ];
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }
}
