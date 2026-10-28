<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Jumlah debit & kredit SEMUA akun pada rentang tanggal, dalam SATU query (jurnal berstatus posted saja).
     * Versi lama menjalankan 1 query per akun (>150 query untuk satu halaman Dashboard).
     *
     * @return array<int, array{debit: float, credit: float}> dikunci dengan account_id
     */
    private function totals(?string $startDate, ?string $endDate): array
    {
        $rows = DB::table('journal_details as d')
            ->join('journals as j', 'j.id', '=', 'd.journal_id')
            ->where('j.status', 'posted')
            ->when($startDate, fn ($q) => $q->whereDate('j.journal_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('j.journal_date', '<=', $endDate))
            ->groupBy('d.account_id')
            ->selectRaw('d.account_id, SUM(d.debit) as debit, SUM(d.credit) as credit')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[$r->account_id] = ['debit' => (float) $r->debit, 'credit' => (float) $r->credit];
        }

        return $map;
    }

    /**
     * Saldo bertanda menurut JENIS akun (bukan saldo normal akun):
     * aset & beban = debit - kredit; kewajiban, ekuitas & pendapatan = kredit - debit.
     * Dengan begitu akun kontra (Akumulasi Penyusutan, Prive) otomatis bernilai NEGATIF dan
     * mengurangi total kelompoknya. Versi lama menjumlahkannya sebagai positif sehingga
     * Neraca tidak seimbang setelah ada penyusutan / prive.
     */
    private function signed(ChartOfAccount $account, array $totals): float
    {
        $t = $totals[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];

        return in_array($account->type, ['aset', 'beban'], true)
            ? round($t['debit'] - $t['credit'], 2)
            : round($t['credit'] - $t['debit'], 2);
    }

    private function section($accounts, array $totals, string $type, bool $withCategory = false)
    {
        return $accounts->where('type', $type)
            ->map(function ($a) use ($totals, $withCategory) {
                $row = ['code' => $a->code, 'name' => $a->name, 'amount' => $this->signed($a, $totals)];
                if ($withCategory) {
                    $row['category'] = $a->category;
                }

                return $row;
            })
            ->filter(fn ($r) => abs($r['amount']) > 0.005)
            ->values();
    }

    /**
     * Neraca Saldo (Trial Balance): saldo debit/kredit tiap akun pada tanggal tertentu.
     * Total debit HARUS SAMA dengan total kredit (bukti keseimbangan buku).
     */
    public function trialBalance(?string $asOfDate = null): array
    {
        $accounts = ChartOfAccount::reportable()->orderBy('code')->get();
        $totals = $this->totals(null, $asOfDate);

        $rows = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($accounts as $account) {
            $t = $totals[$account->id] ?? ['debit' => 0.0, 'credit' => 0.0];
            $net = round($t['debit'] - $t['credit'], 2);
            if (abs($net) < 0.005) {
                continue; // sembunyikan akun bersaldo nol
            }
            $debit = max($net, 0);
            $credit = max(-$net, 0);

            $rows[] = ['code' => $account->code, 'name' => $account->name, 'debit' => $debit, 'credit' => $credit];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => round($totalDebit, 2) === round($totalCredit, 2),
        ];
    }

    /**
     * Laporan Laba Rugi: Pendapatan - Beban = Laba/Rugi Bersih untuk rentang periode
     * (bukan kumulatif seperti neraca).
     */
    public function incomeStatement(string $startDate, string $endDate): array
    {
        $accounts = ChartOfAccount::reportable()->whereIn('type', ['pendapatan', 'beban'])->orderBy('code')->get();
        $totals = $this->totals($startDate, $endDate);

        $revenues = $this->section($accounts, $totals, 'pendapatan');
        $expenses = $this->section($accounts, $totals, 'beban');

        $totalRevenue = round($revenues->sum('amount'), 2);
        $totalExpense = round($expenses->sum('amount'), 2);

        return [
            'period' => [$startDate, $endDate],
            'revenues' => $revenues,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income' => round($totalRevenue - $totalExpense, 2),
        ];
    }

    /**
     * Laporan Posisi Keuangan / Neraca: Aset = Kewajiban + Ekuitas (termasuk laba berjalan
     * yang belum ditutup ke ekuitas).
     */
    public function balanceSheet(string $asOfDate): array
    {
        $accounts = ChartOfAccount::reportable()->orderBy('code')->get();
        $totals = $this->totals(null, $asOfDate);

        $assets = $this->section($accounts, $totals, 'aset', true);
        $liabilities = $this->section($accounts, $totals, 'kewajiban', true);
        $equity = $this->section($accounts, $totals, 'ekuitas');

        // Laba berjalan kumulatif s.d. tanggal neraca (dari data yang sama, tanpa query tambahan).
        $revenue = $accounts->where('type', 'pendapatan')->sum(fn ($a) => $this->signed($a, $totals));
        $expense = $accounts->where('type', 'beban')->sum(fn ($a) => $this->signed($a, $totals));
        $earnings = round($revenue - $expense, 2);

        $totalAssets = round($assets->sum('amount'), 2);
        $totalLiabilities = round($liabilities->sum('amount'), 2);
        $totalEquity = round($equity->sum('amount') + $earnings, 2);

        return [
            'as_of' => $asOfDate,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'current_year_earnings' => $earnings,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'is_balanced' => round($totalAssets - ($totalLiabilities + $totalEquity), 2) === 0.0,
        ];
    }

    /**
     * Buku Besar (General Ledger) per akun: mutasi debit/kredit dengan saldo berjalan.
     */
    public function ledger(ChartOfAccount $account, string $startDate, string $endDate): array
    {
        $openingBalance = $account->getBalance(null, date('Y-m-d', strtotime($startDate.' -1 day')));

        $details = $account->journalDetails()
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', 'posted')
            ->whereDate('journals.journal_date', '>=', $startDate)
            ->whereDate('journals.journal_date', '<=', $endDate)
            ->orderBy('journals.journal_date')
            ->orderBy('journal_details.id')
            ->select('journal_details.*', 'journals.journal_date', 'journals.journal_number', 'journals.description as journal_description')
            ->get();

        $running = $openingBalance;
        $rows = [];
        foreach ($details as $d) {
            $running += $account->normal_balance === 'debit' ? ($d->debit - $d->credit) : ($d->credit - $d->debit);
            $rows[] = [
                'date' => $d->journal_date,
                'journal_number' => $d->journal_number,
                'description' => $d->description ?: $d->journal_description,
                'debit' => (float) $d->debit,
                'credit' => (float) $d->credit,
                'balance' => $running,
            ];
        }

        return [
            'account' => $account,
            'opening_balance' => $openingBalance,
            'rows' => $rows,
            'closing_balance' => $running,
        ];
    }
}
