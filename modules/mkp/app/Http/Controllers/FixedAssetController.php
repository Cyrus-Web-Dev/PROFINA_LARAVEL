<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\FixedAsset;
use App\Services\FixedAssetService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedAssetController extends Controller
{
    public function __construct(private FixedAssetService $fixedAssetService)
    {
    }

    public function index()
    {
        $assets = FixedAsset::orderByDesc('id')->get()->map(fn ($a) => [
            ...$a->toArray(),
            'book_value' => $a->bookValue(),
            'monthly_depreciation' => $a->monthlyDepreciation(),
        ]);

        return Inertia::render('FixedAssets/Index', ['assets' => $assets]);
    }

    public function create()
    {
        return Inertia::render('FixedAssets/Create', [
            'assetAccounts' => ChartOfAccount::postable()->where('category', 'aset_tetap')->orderBy('code')->get(['id', 'code', 'name']),
            'accumulatedDepreciationAccounts' => ChartOfAccount::postable()->where('name', 'like', '%Akumulasi Depresiasi%')->get(['id', 'code', 'name']),
            'depreciationExpenseAccounts' => ChartOfAccount::postable()->where('name', 'like', '%Beban Depresiasi%')->get(['id', 'code', 'name']),
            'paymentAccounts' => ChartOfAccount::postable()->where(fn ($q) => $q->where('is_cash_bank', true)->orWhere('name', 'like', '%Utang%'))->get(['id', 'code', 'name']),
            'nextNumber' => FixedAsset::generateAssetNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0.01',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1',
            'asset_account_id' => 'required|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:chart_of_accounts,id',
            'depreciation_expense_account_id' => 'required|exists:chart_of_accounts,id',
            'payment_account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        $asset = $this->fixedAssetService->acquire($validated, $request->user()->id);

        return redirect()->route('fixed-assets.show', $asset)->with('success', 'Aset tetap berhasil dicatat dan jurnal perolehan diposting.');
    }

    public function show(FixedAsset $fixedAsset)
    {
        $fixedAsset->load('depreciations.journal', 'assetAccount', 'accumulatedDepreciationAccount');

        return Inertia::render('FixedAssets/Show', [
            'asset' => [...$fixedAsset->toArray(), 'book_value' => $fixedAsset->bookValue(), 'monthly_depreciation' => $fixedAsset->monthlyDepreciation()],
        ]);
    }

    public function runDepreciation(Request $request, FixedAsset $fixedAsset)
    {
        $validated = $request->validate(['period_date' => 'required|date']);

        $this->fixedAssetService->runDepreciation($fixedAsset, $validated['period_date'], $request->user()->id);

        return back()->with('success', 'Depresiasi periode ini berhasil diposting.');
    }
}
