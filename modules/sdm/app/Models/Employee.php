<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'nip', 'full_name', 'gender', 'place_of_birth', 'date_of_birth',
        'address', 'phone', 'personal_email', 'marital_status', 'dependents_count',
        'department_id', 'position_id', 'supervisor_id', 'employment_type',
        'join_date', 'contract_end_date', 'resign_date', 'employment_status',
        'base_salary', 'bank_name', 'bank_account_number', 'bank_account_name',
        'npwp', 'bpjs_kesehatan_number', 'bpjs_tk_number', 'ptkp_status',
        'photo_path', 'emergency_contact_name', 'emergency_contact_phone', 'notes',
    ];

    // WAJIB: tanpa ini, photo_url/employment_type_label/employment_status_label tidak akan
    // muncul di JSON yang dikirim ke Vue meski method accessor-nya ada di bawah (Laravel hanya
    // menyertakan kolom asli tabel secara otomatis, bukan accessor turunan).
    protected $appends = ['photo_url', 'employment_type_label', 'employment_status_label'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'join_date' => 'date',
            'contract_end_date' => 'date',
            'resign_date' => 'date',
            'base_salary' => 'decimal:2',
            'dependents_count' => 'integer',
            // Identitas sensitif dienkripsi di database (AES-256 via APP_KEY); otomatis didekripsi saat dibaca.
            'npwp' => 'encrypted',
            'bpjs_kesehatan_number' => 'encrypted',
            'bpjs_tk_number' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    public const EMPLOYMENT_TYPE_LABELS = [
        'tetap' => 'Karyawan Tetap (PKWTT)',
        'kontrak' => 'Kontrak (PKWT)',
        'magang' => 'Magang',
        'harian' => 'Harian Lepas',
    ];

    public const EMPLOYMENT_STATUS_LABELS = [
        'active' => 'Aktif',
        'resigned' => 'Resign',
        'terminated' => 'Diberhentikan',
        'retired' => 'Pensiun',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function trainingParticipations(): HasMany
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! array_key_exists('photo_path', $this->attributes) || ! $this->photo_path) {
            return null;
        }

        return route('employees.photo', ['employee' => $this->id, 'v' => $this->updated_at?->timestamp], false);
    }

    public function getEmploymentTypeLabelAttribute(): ?string
    {
        if (! array_key_exists('employment_type', $this->attributes)) {
            return null; // Baris hasil query agregat/parsial yang tidak menyertakan kolom ini.
        }

        return self::EMPLOYMENT_TYPE_LABELS[$this->employment_type] ?? $this->employment_type;
    }

    public function getEmploymentStatusLabelAttribute(): ?string
    {
        if (! array_key_exists('employment_status', $this->attributes)) {
            return null; // Baris hasil query agregat/parsial yang tidak menyertakan kolom ini.
        }

        return self::EMPLOYMENT_STATUS_LABELS[$this->employment_status] ?? $this->employment_status;
    }
}
