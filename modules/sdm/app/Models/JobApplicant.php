<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplicant extends Model
{
    protected $fillable = [
        'job_vacancy_id', 'full_name', 'email', 'phone', 'resume_path', 'cover_letter',
        'stage', 'score', 'notes', 'source', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
        ];
    }

    public const STAGE_LABELS = [
        'applied' => 'Melamar',
        'screening' => 'Seleksi Administrasi',
        'interview' => 'Wawancara',
        'offer' => 'Penawaran',
        'hired' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }
}
