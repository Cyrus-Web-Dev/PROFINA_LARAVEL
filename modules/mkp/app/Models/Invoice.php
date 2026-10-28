<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasAttachments;
    use HasCleanDateSerialization;

    protected $fillable = [
        'invoice_number', 'customer_id', 'invoice_date', 'due_date', 'description',
        'receivable_account_id', 'subtotal', 'tax_amount', 'total', 'paid_amount',
        'status', 'journal_id', 'created_by',
    ];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivable_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function remainingBalance(): float
    {
        return round($this->total - $this->paid_amount, 2);
    }

    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $last = self::where('invoice_number', 'like', "INV-{$year}-%")->orderByDesc('id')->value('invoice_number');
        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return sprintf('INV-%d-%05d', $year, $seq);
    }
}
