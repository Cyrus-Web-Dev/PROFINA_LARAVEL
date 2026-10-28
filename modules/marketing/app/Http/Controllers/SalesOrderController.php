<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SalesOrderController extends Controller
{
    /** status -> status yang boleh dituju secara manual (partial/delivered diatur oleh surat jalan). */
    private const MANUAL = [
        'confirmed' => ['in_production', 'cancelled'],
        'in_production' => ['ready', 'cancelled'],
        'ready' => ['in_production', 'cancelled'],
    ];

    public function index(Request $request)
    {
        $orders = SalesOrder::query()->visibleTo($request->user())->with(['customer:id,name', 'sales:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('number', 'ilike', '%'.$request->q.'%')->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', '%'.$request->q.'%'))))
            ->latest('id')->paginate(15)->withQueryString()
            ->through(fn (SalesOrder $o) => ['id' => $o->id, 'number' => $o->number, 'customer' => $o->customer?->name, 'sales' => $o->sales?->name, 'status' => $o->status, 'order_date' => $o->order_date?->format('d/m/Y'), 'total' => $o->total]);

        return Inertia::render('Orders/Index', ['orders' => $orders, 'filters' => $request->only('q', 'status')]);
    }

    public function show(Request $request, SalesOrder $order)
    {
        $this->authorizeVisible($request, $order);
        $order->load(['customer', 'sales:id,name', 'items', 'quotation:id,number', 'deliveries']);
        $u = $request->user();

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id, 'number' => $order->number, 'status' => $order->status, 'order_date' => $order->order_date?->format('d/m/Y'),
                'target_date' => $order->target_date?->toDateString(),
                'customer' => ['id' => $order->customer->id, 'name' => $order->customer->name, 'company' => $order->customer->company], 'sales' => $order->sales?->name,
                'quotation' => $order->quotation ? ['id' => $order->quotation->id, 'number' => $order->quotation->number] : null,
                'subtotal' => $order->subtotal, 'tax_pct' => $order->tax_pct, 'tax_amount' => $order->tax_amount, 'total' => $order->total,
                'ship_to' => $order->ship_to, 'notes' => $order->notes,
                'items' => $order->items->map(fn ($i) => ['id' => $i->id, 'description' => $i->description, 'unit' => $i->unit, 'qty' => $i->qty, 'delivered_qty' => $i->delivered_qty, 'unit_price' => $i->unit_price, 'discount_pct' => $i->discount_pct, 'line_total' => $i->line_total])->values(),
                'deliveries' => $order->deliveries->map(fn ($d) => ['id' => $d->id, 'number' => $d->number, 'status' => $d->status, 'delivery_date' => $d->delivery_date?->format('d/m/Y')])->values(),
            ],
            'nextStatuses' => $u->can('orders.manage') ? (self::MANUAL[$order->status] ?? []) : [],
            'canDeliver' => $u->can('deliveries.manage') && in_array($order->status, ['confirmed', 'in_production', 'ready', 'partial'], true),
        ]);
    }

    public function updateStatus(Request $request, SalesOrder $order)
    {
        $data = $request->validate(['status' => 'required|string', 'target_date' => 'nullable|date']);

        DB::transaction(function () use ($order, $data) {
            $o = SalesOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($data['status'], self::MANUAL[$o->status] ?? [], true), 422, "Perubahan {$o->status} → {$data['status']} tidak diizinkan.");
            if ($data['status'] === 'cancelled') {
                abort_if($o->items()->where('delivered_qty', '>', 0)->exists(), 422, 'Sudah ada barang terkirim; SO tidak bisa dibatalkan.');
                abort_if($o->deliveries()->whereIn('status', ['shipped', 'received'])->exists(), 422, 'Ada surat jalan aktif.');
            }
            $o->update(['status' => $data['status']] + (isset($data['target_date']) ? ['target_date' => $data['target_date']] : []));
        });
        ActivityLogger::log('order.status', "SO {$order->number} → {$data['status']}", $order);

        return back()->with('success', 'Status sales order diperbarui.');
    }

    private function authorizeVisible(Request $request, SalesOrder $o): void
    {
        abort_unless($request->user()->can('scope.all') || $o->sales_user_id === $request->user()->id, 403, 'Sales order ini milik sales lain.');
    }
}
