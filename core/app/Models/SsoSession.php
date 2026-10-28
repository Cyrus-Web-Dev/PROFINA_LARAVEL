<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoSession extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'module', 'ip_address', 'user_agent', 'last_seen_at', 'revoked_at', 'revoked_reason'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
