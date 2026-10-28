<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class FinancialRatioService
{
    public function __construct(private FinancialReportService $reportService)
    {
    }

    public function calculate(string $asOfDate, string $periodStart, string $periodEnd): array
    {
        $balanceSheet = $this->reportService->balanceSheet($asOfDate);
        $incomeStatement = $this->reportService->incomeStatement($periodStart, $periodEnd);

        $currentAssets = ChartOfAccount::postable()->where('category', 'aset_lancar')->get()
            ->sum(fn ($a) => $a->getBalance(null, $asOfDate));
        $inventory = ChartOfAccount::postable()->where('category', 'aset_lancar')->where('name', 'like', '%Persediaan%')->get()
            ->sum(fn ($a) => $a->getBalance(null, $asOfDate));
        $currentLiabilities = ChartOfAccount::postable()->where('category', 'kewajiban_lancar')->get()
            ->sum(fn ($a) => $a->getBalance(null, $asOfDate));

        $totalAssets = $balanceSheet['total_assets'];
        $totalLiabilities = $balanceSheet['total_liabilities'];
        $totalEquity = $balanceSheet['total_equity'];
        $netIncome = $incomeStatement['net_income'];
        $revenue = $incomeStatement['total_revenue'];

        $safeDiv = fn ($a, $b) => $b != 0 ? round($a / $b, 2) : null;

        return [
            'as_of_date' => $asOfDate,
            'period' => [$periodStart, $periodEnd],
            'liquidity' => [
                'current_ratio' => $safeDiv($currentAssets, $currentLiabilities),
                'quick_ratio' => $safeDiv($currentAssets - $inventory, $currentLiabilities),
            ],
            'solvency' => [
                'debt_to_equity' => $safeDiv($totalLiabilities, $totalEquity),
                'debt_to_asset' => $safeDiv($totalLiabilities, $totalAssets),
            ],
            'profitability' => [
                'net_profit_margin' => $safeDiv($netIncome, $revenue), // rasio, dikali 100 di frontend jadi %
                'return_on_assets' => $safeDiv($netIncome, $totalAssets),
                'return_on_equity' => $safeDiv($netIncome, $totalEquity),
            ],
            'raw' => [
                'current_assets' => $currentAssets,
                'current_liabilities' => $currentLiabilities,
                'total_assets' => $totalAssets,
                'total_liabilities' => $totalLiabilities,
                'total_equity' => $totalEquity,
                'net_income' => $netIncome,
                'revenue' => $revenue,
            ],
        ];
    }
}
