<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'title', 'file_path', 'file_original_name',
        'issued_at', 'expires_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public const TYPE_LABELS = [
        'contract' => 'Kontrak Kerja',
        'id_card' => 'KTP',
        'diploma' => 'Ijazah',
        'certificate' => 'Sertifikat',
        'npwp' => 'NPWP',
        'bpjs' => 'Kartu BPJS',
        'other' => 'Lainnya',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at && $this->expires_at->isFuture() && now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false) <= $days;
    }
}
