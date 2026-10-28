<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $budgetService)
    {
    }

    public function index(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        return Inertia::render('Budgets/Index', [
            'year' => $year,
            'month' => $month,
            'comparison' => $this->budgetService->compare($year, $month),
        ]);
    }

    public function edit(Request $request)
    {
        $year = (int) ($request->year ?? now()->year);
        $month = (int) ($request->month ?? now()->month);

        $accounts = ChartOfAccount::postable()->whereIn('type', ['pendapatan', 'beban'])->orderBy('code')->get(['id', 'code', 'name', 'type']);
        $budgets = Budget::where('period_year', $year)->where('period_month', $month)->pluck('amount', 'account_id');

        return Inertia::render('Budgets/Edit', [
            'year' => $year,
            'month' => $month,
            'accounts' => $accounts->map(fn ($a) => [...$a->toArray(), 'budget_amount' => (float) ($budgets[$a->id] ?? 0)]),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'items' => 'required|array',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            $this->budgetService->setBudget($item['account_id'], $validated['year'], $validated['month'], $item['amount'] ?? 0);
        }

        return redirect()->route('budgets.index', ['year' => $validated['year'], 'month' => $validated['month']])
            ->with('success', 'Anggaran berhasil disimpan.');
    }
}
