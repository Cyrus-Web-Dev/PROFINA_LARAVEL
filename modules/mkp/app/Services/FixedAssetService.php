<?php

namespace App\Services;

use App\Models\FixedAsset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetService
{
    public function __construct(private JournalService $journalService)
    {
    }

    /**
     * Catat perolehan aset tetap baru. Jurnal: Debit Aset Tetap, Kredit Kas/Bank (atau Utang Usaha jika kredit).
     */
    public function acquire(array $data, int $userId): FixedAsset
    {
        return DB::transaction(function () use ($data, $userId) {
            $asset = FixedAsset::create([
                'asset_number' => FixedAsset::generateAssetNumber(),
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost' => $data['acquisition_cost'],
                'salvage_value' => $data['salvage_value'] ?? 0,
                'useful_life_months' => $data['useful_life_months'],
                'asset_account_id' => $data['asset_account_id'],
                'accumulated_depreciation_account_id' => $data['accumulated_depreciation_account_id'],
                'depreciation_expense_account_id' => $data['depreciation_expense_account_id'],
            ]);

            $lines = [
                ['account_id' => $data['asset_account_id'], 'debit' => $data['acquisition_cost'], 'credit' => 0, 'description' => "Perolehan {$asset->name}"],
                ['account_id' => $data['payment_account_id'], 'debit' => 0, 'credit' => $data['acquisition_cost'], 'description' => "Perolehan {$asset->name}"],
            ];

            $journal = $this->journalService->create([
                'journal_date' => $data['acquisition_date'],
                'type' => 'umum',
                'reference' => $asset->asset_number,
                'description' => "Perolehan aset tetap - {$asset->name}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $asset->update(['acquisition_journal_id' => $journal->id]);

            return $asset;
        });
    }

    /**
     * Jalankan depresiasi untuk satu aset pada satu periode (akhir bulan tertentu).
     * Jurnal: Debit Beban Depresiasi, Kredit Akumulasi Depresiasi.
     */
    public function runDepreciation(FixedAsset $asset, string $periodDate, int $userId)
    {
        // Selalu dicatat di AKHIR bulan, sehingga tanggal berbeda di bulan yang sama tidak bisa menyusut dua kali.
        $period = Carbon::parse($periodDate);
        $periodDate = $period->copy()->endOfMonth()->toDateString();

        if ($period->copy()->endOfMonth()->lt(Carbon::parse($asset->acquisition_date)->startOfMonth())) {
            throw ValidationException::withMessages(['period' => 'Periode depresiasi tidak boleh sebelum bulan perolehan aset.']);
        }

        return DB::transaction(function () use ($asset, $period, $periodDate, $userId) {
            // Kunci baris aset agar klik ganda / dua pengguna tidak menjalankan depresiasi dua kali.
            $asset = FixedAsset::lockForUpdate()->findOrFail($asset->id);

            if ($asset->isFullyDepreciated()) {
                throw ValidationException::withMessages(['asset' => 'Aset ini sudah terdepresiasi penuh.']);
            }
            if ($asset->depreciations()->whereDate('period_date', '>=', $period->copy()->startOfMonth()->toDateString())->whereDate('period_date', '<=', $periodDate)->exists()) {
                throw ValidationException::withMessages(['period' => 'Depresiasi untuk bulan ini sudah pernah dijalankan.']);
            }

            $remainingMonths = $asset->useful_life_months - $asset->months_depreciated;
            $amount = $remainingMonths <= 1
                ? round($asset->acquisition_cost - $asset->salvage_value - $asset->accumulated_depreciation, 2) // pembulatan bulan terakhir
                : $asset->monthlyDepreciation();

            $lines = [
                ['account_id' => $asset->depreciation_expense_account_id, 'debit' => $amount, 'credit' => 0, 'description' => "Depresiasi {$asset->name} - {$periodDate}"],
                ['account_id' => $asset->accumulated_depreciation_account_id, 'debit' => 0, 'credit' => $amount, 'description' => "Depresiasi {$asset->name} - {$periodDate}"],
            ];

            $journal = $this->journalService->create([
                'journal_date' => $periodDate,
                'type' => 'penyesuaian',
                'reference' => $asset->asset_number,
                'description' => "Beban depresiasi - {$asset->name}",
                'created_by' => $userId,
            ], $lines, true);
            $this->journalService->post($journal, $userId);

            $depreciation = $asset->depreciations()->create([
                'period_date' => $periodDate,
                'amount' => $amount,
                'journal_id' => $journal->id,
            ]);

            $asset->update([
                'accumulated_depreciation' => $asset->accumulated_depreciation + $amount,
                'months_depreciated' => $asset->months_depreciated + 1,
                'status' => ($asset->months_depreciated + 1) >= $asset->useful_life_months ? 'fully_depreciated' : 'active',
            ]);

            return $depreciation;
        });
    }

    /**
     * Jalankan depresiasi bulanan untuk SEMUA aset aktif sekaligus (dipanggil manual atau via scheduler).
     */
    public function runMonthlyDepreciationForAll(string $periodDate, int $userId): array
    {
        $results = [];
        foreach (FixedAsset::where('status', 'active')->get() as $asset) {
            try {
                $results[] = $this->runDepreciation($asset, $periodDate, $userId);
            } catch (ValidationException $e) {
                continue; // sudah dijalankan / sudah penuh - lewati
            }
        }

        return $results;
    }
}
