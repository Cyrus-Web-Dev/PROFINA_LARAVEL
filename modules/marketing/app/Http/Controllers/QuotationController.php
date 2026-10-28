<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Services\ActivityLogger;
use App\Services\DocumentNumber;
use App\Services\QuotationCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QuotationController extends Controller
{
    public function index(Request $request)
    {
        $quotations = Quotation::query()->visibleTo($request->user())->with(['customer:id,name', 'sales:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('number', 'ilike', '%'.$request->q.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', '%'.$request->q.'%'))))
            ->latest('id')->paginate(15)->withQueryString()
            ->through(fn (Quotation $x) => [
                'id' => $x->id, 'number' => $x->number, 'customer' => $x->customer?->name, 'sales' => $x->sales?->name, 'status' => $x->status,
                'quote_date' => $x->quote_date?->format('d/m/Y'), 'valid_until' => $x->valid_until?->format('d/m/Y'), 'total' => $x->total,
            ]);

        return Inertia::render('Quotations/Index', ['quotations' => $quotations, 'filters' => $request->only('q', 'status')]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Quotations/Form', $this->formProps($request, null));
    }

    public function store(Request $request, QuotationCalculator $calc)
    {
        [$data, $customer] = $this->validated($request);
        $calculated = $calc->calculate($customer, $data['items'], $request->user(), $this->taxFor($request, $data));

        $q = DB::transaction(function () use ($request, $data, $calculated) {
            $q = Quotation::create([
                'number' => DocumentNumber::next('QT'),
                'customer_id' => $data['customer_id'],
                'sales_user_id' => $request->user()->id,
                'status' => 'draft',
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'],
                'subtotal' => $calculated['subtotal'], 'tax_pct' => $calculated['tax_pct'], 'tax_amount' => $calculated['tax_amount'], 'total' => $calculated['total'],
                'terms' => $data['terms'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $q->items()->createMany($calculated['items']);

            return $q;
        });
        ActivityLogger::log('quotation.created', "Membuat penawaran {$q->number}", $q);

        return redirect()->route('quotations.show', $q)->with('success', "Penawaran {$q->number} dibuat sebagai draft.");
    }

    public function show(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        $quotation->load(['customer.level', 'sales:id,name', 'items', 'order:id,quotation_id,number']);
        $u = $request->user();

        $payload = [
            'quotation' => [
                'id' => $quotation->id, 'number' => $quotation->number, 'status' => $quotation->status,
                'quote_date' => $quotation->quote_date?->format('d/m/Y'), 'valid_until' => $quotation->valid_until?->format('d/m/Y'),
                'customer' => ['id' => $quotation->customer->id, 'name' => $quotation->customer->name, 'company' => $quotation->customer->company, 'address' => $quotation->customer->address, 'level' => $quotation->customer->level?->name],
                'sales' => $quotation->sales?->name,
                'subtotal' => $quotation->subtotal, 'tax_pct' => $quotation->tax_pct, 'tax_amount' => $quotation->tax_amount, 'total' => $quotation->total,
                'terms' => $quotation->terms, 'notes' => $quotation->notes, 'reject_reason' => $quotation->reject_reason,
                'order' => $quotation->order ? ['id' => $quotation->order->id, 'number' => $quotation->order->number] : null,
                'items' => $quotation->items->map(fn ($i) => ['description' => $i->description, 'unit' => $i->unit, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'discount_pct' => $i->discount_pct, 'line_total' => $i->line_total])->values(),
            ],
            'can' => [
                'edit' => $quotation->status === 'draft' && $u->can('quotations.create'),
                'send' => $quotation->status === 'draft' && $u->can('quotations.create'),
                'reopen' => $quotation->status === 'sent' && $u->can('quotations.create'),
                'decide' => $quotation->status === 'sent' && $u->can('quotations.approve'),
            ],
        ];

        // Margin = rahasia dapur: hanya untuk pemegang izin cost.view.
        if ($u->can('cost.view')) {
            $cost = 0;
            foreach ($quotation->items as $i) {
                $cost += (int) round(((float) ($i->product_id ? Product::withTrashed()->whereKey($i->product_id)->value('cost_price') : 0)) * (float) $i->qty * 100);
            }
            $subtotal = (int) round((float) $quotation->subtotal * 100);
            $payload['margin'] = ['cost' => number_format($cost / 100, 2, '.', ''), 'profit' => number_format(($subtotal - $cost) / 100, 2, '.', ''), 'pct' => $subtotal > 0 ? round(($subtotal - $cost) / $subtotal * 100, 1) : 0];
        }

        return Inertia::render('Quotations/Show', $payload);
    }

    public function edit(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        abort_unless($quotation->status === 'draft', 422, 'Hanya draft yang bisa diubah. Tarik kembali ke draft dulu bila sudah terkirim.');

        return Inertia::render('Quotations/Form', $this->formProps($request, $quotation->load('items')));
    }

    public function update(Request $request, Quotation $quotation, QuotationCalculator $calc)
    {
        $this->authorizeVisible($request, $quotation);
        abort_unless($quotation->status === 'draft', 422, 'Hanya draft yang bisa diubah.');

        [$data, $customer] = $this->validated($request);
        $calculated = $calc->calculate($customer, $data['items'], $request->user(), $this->taxFor($request, $data));

        DB::transaction(function () use ($quotation, $data, $calculated) {
            $quotation->update([
                'customer_id' => $data['customer_id'], 'quote_date' => $data['quote_date'], 'valid_until' => $data['valid_until'],
                'subtotal' => $calculated['subtotal'], 'tax_pct' => $calculated['tax_pct'], 'tax_amount' => $calculated['tax_amount'], 'total' => $calculated['total'],
                'terms' => $data['terms'] ?? null, 'notes' => $data['notes'] ?? null,
            ]);
            $quotation->items()->delete();
            $quotation->items()->createMany($calculated['items']);
        });
        ActivityLogger::log('quotation.updated', "Mengubah penawaran {$quotation->number}", $quotation);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Penawaran diperbarui.');
    }

    public function destroy(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        abort_unless($quotation->status === 'draft', 422, 'Hanya draft yang bisa dihapus.');
        $quotation->delete();
        ActivityLogger::log('quotation.deleted', "Menghapus draft penawaran {$quotation->number}", $quotation);

        return redirect()->route('quotations.index')->with('success', 'Draft dihapus.');
    }

    public function send(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        abort_unless($quotation->status === 'draft', 422, 'Penawaran ini bukan draft.');
        abort_if($quotation->valid_until->isPast() && ! $quotation->valid_until->isToday(), 422, 'Masa berlaku sudah lewat. Ubah tanggalnya dulu.');
        $quotation->update(['status' => 'sent', 'sent_at' => now()]);
        ActivityLogger::log('quotation.sent', "Penawaran {$quotation->number} ditandai terkirim", $quotation);

        return back()->with('success', 'Penawaran ditandai terkirim. Unduh PDF untuk dikirim ke pelanggan.');
    }

    public function reopen(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        abort_unless($quotation->status === 'sent', 422, 'Hanya penawaran terkirim yang bisa ditarik.');
        $quotation->update(['status' => 'draft', 'sent_at' => null]);
        ActivityLogger::log('quotation.reopened', "Penawaran {$quotation->number} ditarik ke draft", $quotation);

        return back()->with('success', 'Penawaran kembali ke draft.');
    }

    /** SEKALI KLIK: penawaran disetujui pelanggan -> Sales Order tercipta otomatis (tanpa ketik ulang). */
    public function approve(Request $request, Quotation $quotation)
    {
        $order = DB::transaction(function () use ($request, $quotation) {
            $q = Quotation::whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            abort_unless($q->status === 'sent', 422, 'Hanya penawaran berstatus "terkirim" yang bisa disetujui.');
            abort_if($q->valid_until->isPast() && ! $q->valid_until->isToday(), 422, 'Penawaran sudah kedaluwarsa.');

            $q->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now()]);

            $order = SalesOrder::create([
                'number' => DocumentNumber::next('SO'),
                'quotation_id' => $q->id,
                'customer_id' => $q->customer_id,
                'sales_user_id' => $q->sales_user_id,
                'status' => 'confirmed',
                'order_date' => now()->toDateString(),
                'subtotal' => $q->subtotal, 'tax_pct' => $q->tax_pct, 'tax_amount' => $q->tax_amount, 'total' => $q->total,
                'ship_to' => $q->customer->address,
                'notes' => $q->notes,
            ]);
            foreach ($q->items as $i) {
                $order->items()->create($i->only(['product_id', 'description', 'unit', 'qty', 'unit_price', 'discount_pct', 'line_total', 'sort']));
            }

            return $order;
        });
        ActivityLogger::log('quotation.approved', "Penawaran {$quotation->number} disetujui → {$order->number}", $quotation);

        return redirect()->route('orders.show', $order)->with('success', "Penawaran disetujui. Sales Order {$order->number} otomatis dibuat.");
    }

    public function reject(Request $request, Quotation $quotation)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        abort_unless($quotation->status === 'sent', 422, 'Hanya penawaran terkirim yang bisa ditolak.');
        $quotation->update(['status' => 'rejected', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'reject_reason' => $data['reason'] ?? null]);
        ActivityLogger::log('quotation.rejected', "Penawaran {$quotation->number} ditolak", $quotation);

        return back()->with('success', 'Penawaran ditandai ditolak.');
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $this->authorizeVisible($request, $quotation);
        $quotation->load(['customer.level', 'sales', 'items']);

        return Pdf::loadView('pdf.quotation', ['q' => $quotation, 'co' => config('marketing.company')])
            ->setPaper('a4')->download($quotation->number.'.pdf');
    }

    // ------------------------------------------------------------------

    private function formProps(Request $request, ?Quotation $q): array
    {
        $u = $request->user();

        return [
            'quotation' => $q ? [
                'id' => $q->id, 'number' => $q->number, 'customer_id' => $q->customer_id, 'quote_date' => $q->quote_date->toDateString(), 'valid_until' => $q->valid_until->toDateString(),
                'tax_pct' => $q->tax_pct, 'terms' => $q->terms, 'notes' => $q->notes,
                'items' => $q->items->map(fn ($i) => ['product_id' => $i->product_id, 'description' => $i->description, 'unit' => $i->unit, 'qty' => (float) $i->qty, 'unit_price' => $i->unit_price, 'discount_pct' => (float) $i->discount_pct])->values(),
            ] : null,
            'customers' => Customer::visibleTo($u)->where('status', '!=', 'inactive')->with('level:id,name')->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name.($c->company ? " — {$c->company}" : ''), 'level' => $c->level?->name]),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'base_price', 'min_order_qty']),
            'defaults' => ['quote_date' => now()->toDateString(), 'valid_until' => now()->addDays(config('marketing.quotation_valid_days'))->toDateString(), 'tax_pct' => config('marketing.tax_pct')],
            'canOverride' => $u->can('prices.override'),
            'maxDiscount' => config('marketing.max_discount_pct'),
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'quote_date' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:quote_date',
            'tax_pct' => 'nullable|numeric|min:0|max:100',
            'terms' => 'nullable|string|max:4000',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:200',
            'items.*.product_id' => 'nullable|integer',
            'items.*.description' => 'nullable|string|max:255',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        return [$data, Customer::visibleTo($request->user())->with('level')->findOrFail($data['customer_id'])];
    }

    /** Tarif pajak hanya boleh diubah pemegang prices.override; selain itu ikut konfigurasi. */
    private function taxFor(Request $request, array $data): ?float
    {
        return $request->user()->can('prices.override') && isset($data['tax_pct']) ? (float) $data['tax_pct'] : null;
    }

    private function authorizeVisible(Request $request, Quotation $q): void
    {
        abort_unless($request->user()->can('scope.all') || $q->sales_user_id === $request->user()->id, 403, 'Penawaran ini milik sales lain.');
    }
}
