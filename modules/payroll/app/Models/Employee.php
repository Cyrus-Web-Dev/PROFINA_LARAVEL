<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cermin data karyawan dari SDM. PAYBRIDGE tidak pernah menulis balik ke data kepegawaian. */
class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['npwp', 'bank_account_number', 'bpjs_kesehatan_number', 'bpjs_tk_number', 'birth_date', 'bank_account_hash'];

    protected function casts(): array
    {
        return [
            'join_date' => 'date', 'contract_end_date' => 'date', 'resign_date' => 'date',
            'has_npwp' => 'boolean', 'sdm_base_salary' => 'float',
            'bank_changed_at' => 'datetime', 'synced_at' => 'datetime',
            'npwp' => 'encrypted', 'bank_account_number' => 'encrypted', 'bpjs_kesehatan_number' => 'encrypted',
            'bpjs_tk_number' => 'encrypted', 'birth_date' => 'encrypted',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(CompensationContract::class)->orderByDesc('version');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(CompensationContract::class)->where('status', 'active');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /** HMAC rekening (tanpa spasi/strip) - dipakai membandingkan rekening antar karyawan tanpa membuka isinya. */
    public static function hashAccount(?string $number): ?string
    {
        $n = preg_replace('/\D+/', '', (string) $number);

        return $n === '' ? null : hash_hmac('sha256', $n, (string) config('app.key'));
    }

    public static function maskAccount(?string $number): string
    {
        $n = preg_replace('/\D+/', '', (string) $number);

        return $n === '' ? '-' : str_repeat('•', max(0, strlen($n) - 4)).substr($n, -4);
    }

    /** Kontrak aktif yang berlaku pada tanggal tertentu (memperhitungkan riwayat versi). */
    public function contractOn(string $date): ?CompensationContract
    {
        return $this->contracts()->whereIn('status', ['active', 'superseded', 'expired'])
            ->whereDate('effective_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->where(fn ($q) => $q->whereNull('superseded_on')->orWhereDate('superseded_on', '>', $date))
            ->orderByDesc('effective_date')->first();
    }
}
