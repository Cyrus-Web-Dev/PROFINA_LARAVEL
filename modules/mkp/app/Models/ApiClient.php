<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    protected $fillable = [
        'name', 'system', 'token_prefix', 'token_hash', 'scopes', 'webhook_url', 'webhook_secret', 'webhook_events',
        'is_active', 'last_used_at', 'last_used_ip', 'created_by',
    ];

    protected $hidden = ['token_hash', 'webhook_secret'];

    protected $casts = ['scopes' => 'array', 'webhook_events' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /** Buat token acak. Yang disimpan hanya hash SHA-256-nya; token asli ditampilkan SEKALI saat dibuat. */
    public static function makeToken(): array
    {
        $plain = 'pcr_'.bin2hex(random_bytes(24));

        return [$plain, substr($plain, 0, 12), hash('sha256', $plain)];
    }

    /**
     * Klien sistem Keuangan yang aktif (MKP). Bila ada, keputusan & pencairan dana
     * dilakukan di aplikasi Keuangan lewat API — Meja Keuangan PROCURA hanya pantauan.
     */
    public static function activeFinanceClient(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->where('system', 'keuangan')
            ->orderBy('id')
            ->get()
            ->first(fn (self $c) => $c->can('funding:decide') || $c->can('funding:read'));
    }
}
