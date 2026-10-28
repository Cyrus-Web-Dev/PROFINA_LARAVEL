<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false; // hanya created_at, diisi manual

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'description', 'ip_address', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** Tampil sebagai "2026-09-20 14:05:33" (zona waktu aplikasi), bukan ISO 8601 UTC mentah. */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
