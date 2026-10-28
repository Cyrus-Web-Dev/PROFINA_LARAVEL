<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementReport extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['funding_id', 'direction', 'kind', 'subject', 'body', 'dedupe_key', 'delivery_id', 'read_at', 'created_by'];

    protected $casts = ['read_at' => 'datetime'];

    public function funding(): BelongsTo
    {
        return $this->belongsTo(ProcurementFunding::class, 'funding_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function unread(): int
    {
        return static::where('direction', 'masuk')->whereNull('read_at')->count();
    }

    /** Catat entri; kunci yang sama dalam 10 menit terakhir tidak dibuat ganda (webhook + sinkron bisa datang bersamaan). */
    public static function record(?ProcurementFunding $f, string $direction, string $kind, string $subject, ?string $body = null, ?string $key = null, ?string $deliveryId = null, ?int $userId = null): self
    {
        if ($key && $f) {
            $exists = static::where('funding_id', $f->id)->where('dedupe_key', $key)->where('created_at', '>=', now()->subMinutes(10))->first();
            if ($exists) {
                return $exists;
            }
        }

        return static::create([
            'funding_id' => $f?->id, 'direction' => $direction, 'kind' => $kind, 'subject' => $subject, 'body' => $body,
            'dedupe_key' => $key, 'delivery_id' => $deliveryId, 'created_by' => $userId,
            'read_at' => $direction === 'keluar' ? now() : null,
        ]);
    }
}
