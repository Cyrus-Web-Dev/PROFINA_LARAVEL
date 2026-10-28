<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use HasAttachments;
    use HasCleanDateSerialization;

    use HasFactory;

    protected $fillable = [
        'journal_number', 'journal_date', 'fiscal_period_id', 'type',
        'reference', 'description', 'status', 'total_debit', 'total_credit',
        'created_by', 'posted_by', 'posted_at',
        'approval_status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'posted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function requiresApproval(): bool
    {
        return $this->total_debit >= config('mkp.approval_threshold');
    }

    public function details(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Aturan inti akuntansi: total debit HARUS SAMA dengan total kredit.
     * Dipanggil sebelum posting jurnal.
     */
    public function isBalanced(): bool
    {
        $totalDebit = $this->details->sum('debit');
        $totalCredit = $this->details->sum('credit');

        return round((float) $totalDebit, 2) === round((float) $totalCredit, 2) && $totalDebit > 0;
    }

    public function recalculateTotals(): void
    {
        $this->total_debit = $this->details->sum('debit');
        $this->total_credit = $this->details->sum('credit');
        $this->save();
    }

    public static function generateJournalNumber(string $type = 'umum'): string
    {
        $prefix = match ($type) {
            'penyesuaian' => 'JP',
            'penutup' => 'JT',
            'pembalik' => 'JB',
            default => 'JU',
        };
        $year = now()->year;
        $lastNumber = self::where('journal_number', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('journal_number')->value('journal_number');

        $sequence = $lastNumber ? ((int) substr($lastNumber, -5)) + 1 : 1;

        return sprintf('%s-%d-%05d', $prefix, $year, $sequence);
    }
}
