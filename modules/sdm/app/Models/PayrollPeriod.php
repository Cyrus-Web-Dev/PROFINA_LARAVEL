<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = ['period_month', 'period_year', 'status', 'processed_by', 'processed_at', 'paid_at', 'notes'];

    // WAJIB: tanpa ini, accessor "label" (mis. "Januari 2026") tidak akan ikut terkirim ke Vue
    // saat model ini dikirim langsung (lihat PayrollController::show).
    protected $appends = ['label'];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getLabelAttribute(): ?string
    {
        if (! array_key_exists('period_month', $this->attributes) || ! array_key_exists('period_year', $this->attributes)) {
            return null; // Baris hasil query agregat/parsial yang tidak menyertakan kolom ini.
        }
        $bulan = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        return ($bulan[$this->period_month] ?? $this->period_month).' '.$this->period_year;
    }
}
