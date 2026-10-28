<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['bill_id', 'payment_date', 'amount', 'cash_account_id', 'note', 'journal_id', 'created_by'];
    protected $casts = ['payment_date' => 'date'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cash_account_id');
    }
}
