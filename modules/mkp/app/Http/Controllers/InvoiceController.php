<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\SalesService;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function __construct(private SalesService $salesService)
    {
    }

    public function index(Request $request)
    {
        $invoices = Invoice::with('customer')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return Inertia::render('Invoices/Index', ['invoices' => $invoices, 'filters' => $request->only('status')]);
    }

    public function create()
    {
        return Inertia::render('Invoices/Create', [
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'receivableAccounts' => ChartOfAccount::postable()->where('category', 'aset_lancar')->where('name', 'like', '%Piutang%')->get(['id', 'code', 'name']),
            'revenueAccounts' => ChartOfAccount::postable()->where('type', 'pendapatan')->orderBy('code')->get(['id', 'code', 'name']),
            'taxAccounts' => ChartOfAccount::postable()->where('name', 'like', '%Pajak%')->get(['id', 'code', 'name']),
            'nextNumber' => Invoice::generateInvoiceNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'description' => 'nullable|string',
            'receivable_account_id' => 'required|exists:chart_of_accounts,id',
            'tax_amount' => 'nullable|numeric|min:0',
            'tax_account_id' => 'nullable|exists:chart_of_accounts,id',
            'items' => 'required|array|min:1',
            'items.*.revenue_account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.description' => 'required|string',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|gt:0',
        ]);

        $invoice = $this->salesService->createInvoice($validated, $validated['items'], $request->user()->id);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dibuat dan jurnal otomatis diposting.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('items.revenueAccount', 'payments.cashAccount', 'customer', 'journal');

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'attachments' => Attachment::panelFor($invoice),
            'cashBankAccounts' => ChartOfAccount::cashBank()->get(['id', 'code', 'name']),
        ]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'cash_account_id' => 'required|exists:chart_of_accounts,id',
            'note' => 'nullable|string',
        ]);

        $this->salesService->recordPayment($invoice, $validated, $request->user()->id);

        return back()->with('success', 'Pembayaran berhasil dicatat dan jurnal otomatis diposting.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->salesService->voidInvoice($invoice, $request->user()->id);

        return back()->with('success', 'Invoice berhasil dibatalkan (jurnal pembalik otomatis diposting).');
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load('items', 'customer');
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'company' => CompanyProfile::current()]);

        return $pdf->download("{$invoice->invoice_number}.pdf");
    }
}
