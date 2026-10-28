<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAssignment extends Model
{
    protected $fillable = ['user_id', 'module', 'role', 'permissions', 'granted_by'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
