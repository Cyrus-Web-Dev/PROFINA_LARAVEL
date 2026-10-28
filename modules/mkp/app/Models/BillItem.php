<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $fillable = ['bill_id', 'expense_account_id', 'description', 'qty', 'price', 'amount'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }
}
