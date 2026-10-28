<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Services\CashBankService;
use App\Services\FinancialReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashBankController extends Controller
{
    public function __construct(private CashBankService $cashBankService, private FinancialReportService $reportService)
    {
    }

    public function index(Request $request)
    {
        $asOfDate = $request->as_of_date ?? now()->toDateString();

        return Inertia::render('CashBank/Index', [
            'summary' => $this->cashBankService->summary($asOfDate),
            'accounts' => ChartOfAccount::cashBank()->get(['id', 'code', 'name']),
            'asOfDate' => $asOfDate,
        ]);
    }

    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'from_account_id' => 'required|exists:chart_of_accounts,id',
            'to_account_id' => 'required|exists:chart_of_accounts,id|different:from_account_id',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string',
        ]);

        $this->cashBankService->transfer($validated, $request->user()->id);

        return back()->with('success', 'Transfer berhasil dicatat dan jurnal otomatis diposting.');
    }
}
