<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationClient extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'webhook_secret'];

    protected $casts = ['scopes' => 'array', 'webhook_events' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];

    public const SCOPES = [
        'employees:read' => 'Baca data karyawan & departemen (tanpa data sensitif)',
        'employees:sensitive' => 'Baca NPWP, rekening bank, nomor BPJS, tanggal lahir',
        'attendance:read' => 'Baca ringkasan absensi/cuti & kalender libur',
    ];

    public const EVENTS = [
        'employee.created' => 'Karyawan baru', 'employee.updated' => 'Data karyawan berubah (nama kolom saja)',
        'employee.status_changed' => 'Status kerja / resign berubah', 'employee.bank_changed' => 'Rekening bank berubah (tanpa nomor)',
        'employee.compensation_changed' => 'Gaji pokok berubah (menjadi USULAN kontrak di Payroll)',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(IntegrationDelivery::class);
    }

    public function can(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }

    /** @return array{0:string,1:string,2:string} token, prefix, hash */
    public static function makeToken(): array
    {
        $plain = 'sdm_'.bin2hex(random_bytes(24));

        return [$plain, substr($plain, 0, 12), hash('sha256', $plain)];
    }
}
