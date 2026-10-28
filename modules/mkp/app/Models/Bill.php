<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use HasAttachments;
    use HasCleanDateSerialization;

    protected $fillable = [
        'bill_number', 'vendor_id', 'bill_date', 'due_date', 'description',
        'payable_account_id', 'subtotal', 'tax_amount', 'total', 'paid_amount',
        'status', 'journal_id', 'created_by',
    ];

    protected $casts = ['bill_date' => 'date', 'due_date' => 'date'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payable_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function remainingBalance(): float
    {
        return round($this->total - $this->paid_amount, 2);
    }

    public static function generateBillNumber(): string
    {
        $year = now()->year;
        $last = self::where('bill_number', 'like', "BILL-{$year}-%")->orderByDesc('id')->value('bill_number');
        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('BILL-%d-%05d', $year, $seq);
    }
}
