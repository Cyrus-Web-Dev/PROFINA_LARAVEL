<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'category', 'normal_balance',
        'parent_id', 'description', 'is_active', 'is_header', 'is_cash_bank',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_header' => 'boolean',
        'is_cash_bank' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalDetails(): HasMany
    {
        return $this->hasMany(JournalDetail::class, 'account_id');
    }

    /**
     * Hitung saldo akun (hanya jurnal berstatus posted) pada rentang tanggal tertentu.
     * Mengikuti kaidah akuntansi: saldo normal debit -> saldo = debit - kredit,
     * saldo normal kredit -> saldo = kredit - debit.
     */
    public function getBalance(?string $startDate = null, ?string $endDate = null): float
    {
        $query = $this->journalDetails()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', 'posted');

        if ($startDate) {
            $query->whereDate('journals.journal_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('journals.journal_date', '<=', $endDate);
        }

        $sums = $query->selectRaw('COALESCE(SUM(journal_details.debit),0) as total_debit, COALESCE(SUM(journal_details.credit),0) as total_credit')->first();

        return $this->normal_balance === 'debit'
            ? (float) $sums->total_debit - (float) $sums->total_credit
            : (float) $sums->total_credit - (float) $sums->total_debit;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePostable($query)
    {
        // Akun header (kelompok) tidak boleh dipakai untuk transaksi jurnal
        return $query->where('is_header', false)->where('is_active', true);
    }

    /**
     * Untuk LAPORAN: semua akun non-header, termasuk yang sudah dinonaktifkan.
     * (Akun nonaktif yang masih punya saldo tidak boleh hilang dari Neraca Saldo/Neraca,
     * kalau tidak laporan menjadi tidak seimbang.)
     */
    public function scopeReportable($query)
    {
        return $query->where('is_header', false);
    }

    public function scopeCashBank($query)
    {
        return $query->where('is_cash_bank', true)->where('is_active', true);
    }
}
