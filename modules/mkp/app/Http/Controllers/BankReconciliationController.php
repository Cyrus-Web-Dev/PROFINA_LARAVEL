<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\BankReconciliationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BankReconciliationController extends Controller
{
    public function __construct(private BankReconciliationService $service)
    {
    }

    public function index(Request $request)
    {
        $accountId = $request->account_id ?? ChartOfAccount::cashBank()->value('id');
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();
        $statementEndingBalance = (float) ($request->statement_ending_balance ?? 0);

        $transactions = null;
        $summary = null;
        $account = null;
        if ($accountId) {
            $account = ChartOfAccount::find($accountId);
            if ($account) {
                $transactions = $this->service->transactions($account, $startDate, $endDate);
                $summary = $this->service->summary($account, $endDate, $statementEndingBalance);
            }
        }

        return Inertia::render('BankReconciliation/Index', [
            'accounts' => ChartOfAccount::cashBank()->get(['id', 'code', 'name']),
            'selectedAccountId' => (int) $accountId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'statementEndingBalance' => $statementEndingBalance,
            'transactions' => $transactions,
            'summary' => $summary,
        ]);
    }

    public function markReconciled(Request $request)
    {
        $validated = $request->validate([
            'journal_detail_ids' => 'required|array|min:1',
            'journal_detail_ids.*' => 'integer',
            'reconciled' => 'required|boolean',
        ]);

        $this->service->markReconciled($validated['journal_detail_ids'], $validated['reconciled']);

        return back()->with('success', $validated['reconciled'] ? 'Transaksi ditandai sudah direkonsiliasi.' : 'Tanda rekonsiliasi dibatalkan.');
    }
}
