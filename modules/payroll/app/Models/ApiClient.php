<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'webhook_secret'];

    protected $casts = ['scopes' => 'array', 'webhook_events' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];

    /** Daftar scope & event yang dikenal (dipakai form Integrasi). */
    public const SCOPES = [
        'funding:read' => 'Baca pengajuan dana gaji', 'funding:decide' => 'Putuskan, cairkan & kirim catatan pengajuan dana',
        'remittance:read' => 'Baca kewajiban setoran pajak/BPJS', 'remittance:update' => 'Catat setoran pajak/BPJS terbayar',
        'budget:read' => 'Baca anggaran SDM aktif', 'contracts:read' => 'Baca status kontrak kompensasi',
        'payslips:read' => 'Baca ringkasan slip gaji (tanpa rincian)', 'audit:read' => 'Baca rantai audit & hash terkini',
    ];

    public const EVENTS = [
        'funding.submitted' => 'Pengajuan dana gaji masuk', 'funding.cancelled' => 'Pengajuan dana dibatalkan',
        'remittance.created' => 'Kewajiban setoran baru', 'budget.approved' => 'Anggaran SDM disetujui',
        'contract.activated' => 'Kontrak kompensasi aktif', 'contract.rejected' => 'Kontrak kompensasi ditolak',
        'payslip.published' => 'Slip gaji terbit', 'run.paid' => 'Gaji terbayar',
    ];

    public const PRESETS = [
        'keuangan' => ['scopes' => ['funding:read', 'funding:decide', 'remittance:read', 'remittance:update', 'budget:read'], 'events' => ['funding.submitted', 'funding.cancelled', 'remittance.created', 'budget.approved']],
        'sdm' => ['scopes' => ['contracts:read', 'payslips:read'], 'events' => ['contract.activated', 'contract.rejected', 'payslip.published', 'run.paid']],
        'audit' => ['scopes' => ['audit:read', 'contracts:read'], 'events' => []],
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /** Token acak; yang disimpan hanya hash SHA-256-nya. @return array{0:string,1:string,2:string} */
    public static function makeToken(): array
    {
        $plain = 'pby_'.bin2hex(random_bytes(24));

        return [$plain, substr($plain, 0, 12), hash('sha256', $plain)];
    }
}
