<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = ['invoice_id', 'revenue_account_id', 'description', 'qty', 'price', 'amount'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'revenue_account_id');
    }
}
