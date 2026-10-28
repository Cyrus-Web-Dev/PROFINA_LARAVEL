<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id', 'date', 'check_in', 'check_out',
        'check_in_lat', 'check_in_lng', 'check_out_lat', 'check_out_lng',
        'status', 'late_minutes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public const STATUS_LABELS = [
        'hadir' => 'Hadir',
        'terlambat' => 'Terlambat',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'alpha' => 'Alpha',
        'cuti' => 'Cuti',
        'wfh' => 'WFH',
        'libur' => 'Libur',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
