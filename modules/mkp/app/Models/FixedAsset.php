<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = [
        'asset_number', 'name', 'category', 'acquisition_date', 'acquisition_cost', 'salvage_value',
        'useful_life_months', 'depreciation_method', 'asset_account_id', 'accumulated_depreciation_account_id',
        'depreciation_expense_account_id', 'accumulated_depreciation', 'months_depreciated', 'status', 'acquisition_journal_id',
    ];

    protected $casts = ['acquisition_date' => 'date'];

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class);
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    /** Depresiasi bulanan metode garis lurus: (Harga Perolehan - Nilai Residu) / Umur Ekonomis (bulan) */
    public function monthlyDepreciation(): float
    {
        return round(($this->acquisition_cost - $this->salvage_value) / max($this->useful_life_months, 1), 2);
    }

    public function bookValue(): float
    {
        return round($this->acquisition_cost - $this->accumulated_depreciation, 2);
    }

    public function isFullyDepreciated(): bool
    {
        return $this->months_depreciated >= $this->useful_life_months;
    }

    public static function generateAssetNumber(): string
    {
        $year = now()->year;
        $last = self::where('asset_number', 'like', "FA-{$year}-%")->orderByDesc('id')->value('asset_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('FA-%d-%04d', $year, $seq);
    }
}
