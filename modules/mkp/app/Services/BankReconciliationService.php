<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalDetail;

class BankReconciliationService
{
    /**
     * Ambil semua baris mutasi (jurnal posted) untuk satu akun Kas/Bank pada rentang tanggal,
     * lengkap dengan status rekonsiliasi masing-masing baris.
     */
    public function transactions(ChartOfAccount $account, string $startDate, string $endDate)
    {
        return JournalDetail::where('account_id', $account->id)
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', 'posted')
            ->whereDate('journals.journal_date', '>=', $startDate)
            ->whereDate('journals.journal_date', '<=', $endDate)
            ->orderBy('journals.journal_date')
            ->select('journal_details.*', 'journals.journal_date', 'journals.journal_number', 'journals.description as journal_description')
            ->get();
    }

    /**
     * Tandai sekumpulan baris jurnal sebagai sudah direkonsiliasi (dicocokkan dengan rekening koran bank).
     */
    public function markReconciled(array $journalDetailIds, bool $reconciled = true): int
    {
        return JournalDetail::whereIn('id', $journalDetailIds)->update([
            'is_reconciled' => $reconciled,
            'reconciled_at' => $reconciled ? now() : null,
        ]);
    }

    /**
     * Hitung saldo menurut buku (semua transaksi posted) vs saldo yang sudah direkonsiliasi,
     * untuk dibandingkan dengan saldo akhir rekening koran bank yang diinput pengguna.
     */
    public function summary(ChartOfAccount $account, string $endDate, float $statementEndingBalance): array
    {
        $bookBalance = $account->getBalance(null, $endDate);

        $unreconciledDebit = JournalDetail::where('account_id', $account->id)
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', 'posted')->where('journal_details.is_reconciled', false)
            ->whereDate('journals.journal_date', '<=', $endDate)
            ->sum('journal_details.debit');
        $unreconciledCredit = JournalDetail::where('account_id', $account->id)
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.status', 'posted')->where('journal_details.is_reconciled', false)
            ->whereDate('journals.journal_date', '<=', $endDate)
            ->sum('journal_details.credit');

        $unreconciledNet = $account->normal_balance === 'debit'
            ? $unreconciledDebit - $unreconciledCredit
            : $unreconciledCredit - $unreconciledDebit;

        $reconciledBalance = $bookBalance - $unreconciledNet;
        $difference = round($statementEndingBalance - $reconciledBalance, 2);

        return [
            'book_balance' => $bookBalance,
            'reconciled_balance' => $reconciledBalance,
            'unreconciled_amount' => $unreconciledNet,
            'statement_ending_balance' => $statementEndingBalance,
            'difference' => $difference,
            'is_reconciled' => abs($difference) < 0.005,
        ];
    }
}
