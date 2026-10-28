<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\ChartOfAccount;

class BudgetService
{
    /**
     * Simpan/perbarui anggaran satu akun untuk satu bulan tertentu (upsert).
     */
    public function setBudget(int $accountId, int $year, int $month, float $amount): Budget
    {
        return Budget::updateOrCreate(
            ['account_id' => $accountId, 'period_year' => $year, 'period_month' => $month],
            ['amount' => $amount]
        );
    }

    /**
     * Bandingkan anggaran vs realisasi untuk akun-akun Pendapatan & Beban pada satu bulan.
     * Realisasi diambil dari saldo aktual akun (getBalance) pada rentang bulan tersebut.
     */
    public function compare(int $year, int $month): array
    {
        $startDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $accounts = ChartOfAccount::postable()->whereIn('type', ['pendapatan', 'beban'])->orderBy('code')->get();
        $budgets = Budget::where('period_year', $year)->where('period_month', $month)->pluck('amount', 'account_id');

        $rows = [];
        foreach ($accounts as $account) {
            $budgetAmount = (float) ($budgets[$account->id] ?? 0);
            $actualAmount = $account->getBalance($startDate, $endDate);
            if ($budgetAmount == 0 && abs($actualAmount) < 0.005) {
                continue;
            }
            $variance = $actualAmount - $budgetAmount;
            // Untuk Beban: realisasi > anggaran = tidak baik (over-budget). Untuk Pendapatan: realisasi > anggaran = baik.
            $isFavorable = $account->type === 'pendapatan' ? $variance >= 0 : $variance <= 0;

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'budget' => $budgetAmount,
                'actual' => $actualAmount,
                'variance' => $variance,
                'variance_pct' => $budgetAmount != 0 ? round($variance / $budgetAmount * 100, 1) : null,
                'is_favorable' => $isFavorable,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'rows' => $rows,
            'total_budget_revenue' => collect($rows)->where('type', 'pendapatan')->sum('budget'),
            'total_actual_revenue' => collect($rows)->where('type', 'pendapatan')->sum('actual'),
            'total_budget_expense' => collect($rows)->where('type', 'beban')->sum('budget'),
            'total_actual_expense' => collect($rows)->where('type', 'beban')->sum('actual'),
        ];
    }
}
