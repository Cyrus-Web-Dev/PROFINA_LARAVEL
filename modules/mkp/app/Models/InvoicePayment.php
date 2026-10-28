<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['invoice_id', 'payment_date', 'amount', 'cash_account_id', 'note', 'journal_id', 'created_by'];
    protected $casts = ['payment_date' => 'date'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_account_id');
    }
}
