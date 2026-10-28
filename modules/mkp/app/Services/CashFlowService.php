<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class CashFlowService
{
    public function __construct(private FinancialReportService $reportService)
    {
    }

    /**
     * Laporan Arus Kas metode tidak langsung (indirect method):
     * Kas dari Operasi = Laba Bersih + Depresiasi (beban non-kas) +/- perubahan modal kerja
     * Kas dari Investasi = perubahan pada Aset Tetap
     * Kas dari Pendanaan = perubahan pada Ekuitas & Kewajiban Jangka Panjang
     */
    public function cashFlowStatement(string $startDate, string $endDate): array
    {
        $priorDate = date('Y-m-d', strtotime($startDate.' -1 day'));

        $income = $this->reportService->incomeStatement($startDate, $endDate);
        $netIncome = $income['net_income'];

        // Depresiasi (beban non-kas) - diasumsikan nama akun mengandung "Depresiasi"
        $depreciation = ChartOfAccount::postable()->where('type', 'beban')->where('name', 'like', '%Depresiasi%')->get()
            ->sum(fn ($a) => $a->getBalance($startDate, $endDate));

        // Perubahan modal kerja: aset lancar non-kas & kewajiban jangka pendek
        $workingCapitalItems = [];
        $workingCapitalTotal = 0;
        $currentAssets = ChartOfAccount::postable()->where('category', 'aset_lancar')->where('is_cash_bank', false)->get();
        foreach ($currentAssets as $acc) {
            $change = $acc->getBalance(null, $endDate) - $acc->getBalance(null, $priorDate);
            if (abs($change) < 0.005) {
                continue;
            }
            $impact = -$change; // kenaikan aset = kas keluar
            $workingCapitalItems[] = ['name' => $acc->name, 'change' => $change, 'impact' => $impact];
            $workingCapitalTotal += $impact;
        }
        $currentLiabilities = ChartOfAccount::postable()->where('category', 'kewajiban_lancar')->get();
        foreach ($currentLiabilities as $acc) {
            $change = $acc->getBalance(null, $endDate) - $acc->getBalance(null, $priorDate);
            if (abs($change) < 0.005) {
                continue;
            }
            $impact = $change; // kenaikan kewajiban = kas masuk
            $workingCapitalItems[] = ['name' => $acc->name, 'change' => $change, 'impact' => $impact];
            $workingCapitalTotal += $impact;
        }

        $operatingCashFlow = $netIncome + $depreciation + $workingCapitalTotal;

        // Investasi: perubahan pada aset tetap (selain akumulasi depresiasi, karena non-kas)
        $investingItems = [];
        $investingTotal = 0;
        $fixedAssets = ChartOfAccount::postable()->where('category', 'aset_tetap')
            ->where('normal_balance', 'debit') // exclude akun kontra akumulasi depresiasi (saldo normal kredit)
            ->get();
        foreach ($fixedAssets as $acc) {
            $change = $acc->getBalance(null, $endDate) - $acc->getBalance(null, $priorDate);
            if (abs($change) < 0.005) {
                continue;
            }
            $impact = -$change; // kenaikan aset tetap = kas keluar (pembelian)
            $investingItems[] = ['name' => $acc->name, 'change' => $change, 'impact' => $impact];
            $investingTotal += $impact;
        }

        // Pendanaan: perubahan ekuitas & kewajiban jangka panjang
        $financingItems = [];
        $financingTotal = 0;
        $longTermLiabilities = ChartOfAccount::postable()->where('category', 'kewajiban_panjang')->get();
        $equityAccounts = ChartOfAccount::postable()->where('type', 'ekuitas')->get();
        foreach ($longTermLiabilities->merge($equityAccounts) as $acc) {
            $change = $acc->getBalance(null, $endDate) - $acc->getBalance(null, $priorDate);
            if (abs($change) < 0.005) {
                continue;
            }
            $impact = $acc->normal_balance === 'kredit' ? $change : -$change;
            $financingItems[] = ['name' => $acc->name, 'change' => $change, 'impact' => $impact];
            $financingTotal += $impact;
        }

        $netCashChange = $operatingCashFlow + $investingTotal + $financingTotal;

        // Validasi silang: bandingkan dengan perubahan saldo kas/bank aktual pada periode ini
        $actualCashChange = ChartOfAccount::cashBank()->get()
            ->sum(fn ($a) => $a->getBalance(null, $endDate) - $a->getBalance(null, $priorDate));

        return [
            'period' => [$startDate, $endDate],
            'net_income' => $netIncome,
            'depreciation' => $depreciation,
            'working_capital_items' => $workingCapitalItems,
            'operating_cash_flow' => $operatingCashFlow,
            'investing_items' => $investingItems,
            'investing_cash_flow' => $investingTotal,
            'financing_items' => $financingItems,
            'financing_cash_flow' => $financingTotal,
            'net_cash_change' => $netCashChange,
            'actual_cash_change' => $actualCashChange,
            'is_reconciled' => round($netCashChange, 2) === round($actualCashChange, 2),
        ];
    }
}
