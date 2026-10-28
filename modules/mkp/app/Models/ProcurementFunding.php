<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementFunding extends Model
{
    use HasAttachments;

    protected $fillable = [
        'procura_number', 'request_number', 'request_title', 'category', 'amount', 'purpose', 'status', 'submitted_at',
        'finance_ref', 'approved_amount', 'decided_by_name', 'decided_by', 'decided_at', 'decision_note',
        'disbursed_amount', 'disbursed_at', 'disbursement_method', 'disbursement_ref', 'cash_account_id', 'disbursement_journal_id',
        'actual_spent', 'returned_amount', 'settled_at', 'settlement_account_id', 'settlement_journal_id', 'settlement_booked_at',
        'snapshot', 'synced_at', 'last_error',
    ];

    protected $casts = [
        'amount' => 'float', 'approved_amount' => 'float', 'disbursed_amount' => 'float', 'actual_spent' => 'float', 'returned_amount' => 'float',
        'submitted_at' => 'datetime', 'decided_at' => 'datetime', 'disbursed_at' => 'datetime', 'settled_at' => 'datetime',
        'settlement_booked_at' => 'datetime', 'synced_at' => 'datetime', 'snapshot' => 'array',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(ProcurementReport::class, 'funding_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_account_id');
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'settlement_account_id');
    }

    public function disbursementJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'disbursement_journal_id');
    }

    public function settlementJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'settlement_journal_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Sisa dana yang masih di tangan Pengadaan menurut catatan Keuangan (cair - terpakai/dikembalikan). */
    public function outstanding(): float
    {
        if ($this->status === 'closed' || $this->settled_at) {
            return 0.0;
        }

        return (float) ($this->disbursed_amount ?? 0);
    }

    /** Nomor dokumen Keuangan berurutan per bulan, mis. KDP-2610-0001. */
    public static function generateRef(): string
    {
        $prefix = 'KDP-'.now()->format('ym').'-';
        $last = static::where('finance_ref', 'like', $prefix.'%')->orderByDesc('finance_ref')->value('finance_ref');

        return $prefix.str_pad((string) (((int) substr((string) $last, -4)) + 1), 4, '0', STR_PAD_LEFT);
    }
}
