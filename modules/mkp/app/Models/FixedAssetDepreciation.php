<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedAssetDepreciation extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['fixed_asset_id', 'period_date', 'amount', 'journal_id'];
    protected $casts = ['period_date' => 'date'];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
