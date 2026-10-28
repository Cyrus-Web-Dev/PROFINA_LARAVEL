<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LedgerController extends Controller
{
    public function __construct(private FinancialReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $accountId = $request->account_id ?? ChartOfAccount::postable()->orderBy('code')->value('id');
        $startDate = $request->start_date ?? now()->startOfYear()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $ledger = null;
        if ($accountId) {
            $account = ChartOfAccount::find($accountId);
            $ledger = $account ? $this->reportService->ledger($account, $startDate, $endDate) : null;
        }

        return Inertia::render('Ledger/Show', [
            'accounts' => ChartOfAccount::postable()->orderBy('code')->get(['id', 'code', 'name']),
            'selectedAccountId' => (int) $accountId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'ledger' => $ledger,
        ]);
    }
}
