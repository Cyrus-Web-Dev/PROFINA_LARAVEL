<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\ChartOfAccount;
use App\Models\Vendor;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Inertia\Inertia;

class BillController extends Controller
{
    public function __construct(private PurchaseService $purchaseService)
    {
    }

    public function index(Request $request)
    {
        $bills = Bill::with('vendor')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Bills/Index', ['bills' => $bills, 'filters' => $request->only('status')]);
    }

    public function create()
    {
        return Inertia::render('Bills/Create', [
            'vendors' => Vendor::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'payableAccounts' => ChartOfAccount::postable()->where('name', 'like', '%Utang Usaha%')->get(['id', 'code', 'name']),
            'expenseAccounts' => ChartOfAccount::postable()->whereIn('type', ['beban', 'aset'])->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'taxAccounts' => ChartOfAccount::postable()->where('name', 'like', '%Pajak%')->get(['id', 'code', 'name']),
            'nextNumber' => Bill::generateBillNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'description' => 'nullable|string',
            'payable_account_id' => 'required|exists:chart_of_accounts,id',
            'tax_amount' => 'nullable|numeric|min:0',
            'tax_account_id' => 'nullable|exists:chart_of_accounts,id',
            'items' => 'required|array|min:1',
            'items.*.expense_account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|gt:0',
        ]);

        $bill = $this->purchaseService->createBill($validated, $validated['items'], $request->user()->id);

        return redirect()->route('bills.show', $bill)->with('success', 'Bill berhasil dibuat dan jurnal otomatis diposting.');
    }

    public function show(Bill $bill)
    {
        $bill->load('items.expenseAccount', 'payments.cashAccount', 'vendor', 'journal');

        return Inertia::render('Bills/Show', [
            'bill' => $bill,
            'attachments' => Attachment::panelFor($bill),
            'cashBankAccounts' => ChartOfAccount::cashBank()->get(['id', 'code', 'name']),
        ]);
    }

    public function pay(Request $request, Bill $bill)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'cash_account_id' => 'required|exists:chart_of_accounts,id',
            'note' => 'nullable|string',
        ]);

        $this->purchaseService->recordPayment($bill, $validated, $request->user()->id);

        return back()->with('success', 'Pembayaran berhasil dicatat dan jurnal otomatis diposting.');
    }

    public function void(Request $request, Bill $bill)
    {
        $this->purchaseService->voidBill($bill, $request->user()->id);

        return back()->with('success', 'Bill berhasil dibatalkan (jurnal pembalik otomatis diposting).');
    }
}
