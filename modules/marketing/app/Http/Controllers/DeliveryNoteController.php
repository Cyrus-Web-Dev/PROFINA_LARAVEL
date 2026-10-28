<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\ActivityLogger;
use App\Services\DocumentNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DeliveryNoteController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $notes = DeliveryNote::query()->with(['customer:id,name', 'order:id,number,sales_user_id'])
            ->when(! $u->can('scope.all'), fn ($q) => $q->whereHas('order', fn ($o) => $o->where('sales_user_id', $u->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where('number', 'ilike', '%'.$request->q.'%'))
            ->latest('id')->paginate(15)->withQueryString()
            ->through(fn (DeliveryNote $d) => ['id' => $d->id, 'number' => $d->number, 'order' => $d->order?->number, 'customer' => $d->customer?->name, 'status' => $d->status, 'delivery_date' => $d->delivery_date?->format('d/m/Y')]);

        return Inertia::render('Deliveries/Index', ['notes' => $notes, 'filters' => $request->only('q', 'status')]);
    }

    public function create(Request $request, SalesOrder $order)
    {
        $this->authorizeOrder($request, $order);
        abort_unless(in_array($order->status, ['confirmed', 'in_production', 'ready', 'partial'], true), 422, 'Sales order ini tidak bisa dikirim (status '.$order->status.').');

        $order->load(['customer:id,name', 'items']);

        return Inertia::render('Deliveries/Create', [
            'order' => [
                'id' => $order->id, 'number' => $order->number, 'customer' => $order->customer->name, 'ship_to' => $order->ship_to,
                'items' => $order->items->map(fn ($i) => ['id' => $i->id, 'description' => $i->description, 'unit' => $i->unit, 'qty' => $i->qty, 'delivered_qty' => $i->delivered_qty, 'outstanding' => number_format(max(0, (float) $i->qty - (float) $i->delivered_qty), 3, '.', '')])->values(),
            ],
        ]);
    }

    public function store(Request $request, SalesOrder $order)
    {
        $this->authorizeOrder($request, $order);
        $data = $request->validate([
            'delivery_date' => 'required|date',
            'driver' => 'nullable|string|max:100',
            'vehicle_no' => 'nullable|string|max:20',
            'ship_to' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:0',
        ]);

        $lines = collect($data['items'])->filter(fn ($l) => (float) $l['qty'] > 0)->values();
        abort_if($lines->isEmpty(), 422, 'Isi qty kirim minimal satu item.');

        $note = DB::transaction(function () use ($request, $order, $data, $lines) {
            $items = SalesOrderItem::where('sales_order_id', $order->id)->whereIn('id', $lines->pluck('sales_order_item_id'))->get()->keyBy('id');
            foreach ($lines as $l) {
                $it = $items[$l['sales_order_item_id']] ?? null;
                abort_unless($it, 422, 'Item tidak termasuk sales order ini.');
                abort_if((float) $l['qty'] > (float) $it->qty - (float) $it->delivered_qty + 1e-9, 422, "Qty kirim {$it->description} melebihi sisa pesanan.");
            }
            $note = DeliveryNote::create([
                'number' => DocumentNumber::next('SJ'), 'sales_order_id' => $order->id, 'customer_id' => $order->customer_id, 'status' => 'draft',
                'delivery_date' => $data['delivery_date'], 'driver' => $data['driver'] ?? null, 'vehicle_no' => $data['vehicle_no'] ?? null,
                'ship_to' => $data['ship_to'] ?? $order->ship_to, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id,
            ]);
            foreach ($lines as $l) {
                $note->items()->create(['sales_order_item_id' => $l['sales_order_item_id'], 'qty' => $l['qty']]);
            }

            return $note;
        });
        ActivityLogger::log('delivery.created', "Surat jalan {$note->number} untuk {$order->number}", $note);

        return redirect()->route('deliveries.show', $note)->with('success', "Surat jalan {$note->number} dibuat (draft).");
    }

    public function show(Request $request, DeliveryNote $delivery)
    {
        $delivery->load(['order:id,number,sales_user_id', 'customer', 'items.orderItem']);
        $this->authorizeOrder($request, $delivery->order);
        $u = $request->user();

        return Inertia::render('Deliveries/Show', [
            'note' => [
                'id' => $delivery->id, 'number' => $delivery->number, 'status' => $delivery->status, 'delivery_date' => $delivery->delivery_date?->format('d/m/Y'),
                'order' => ['id' => $delivery->order->id, 'number' => $delivery->order->number], 'customer' => $delivery->customer->name,
                'driver' => $delivery->driver, 'vehicle_no' => $delivery->vehicle_no, 'ship_to' => $delivery->ship_to, 'notes' => $delivery->notes,
                'received_by' => $delivery->received_by, 'received_at' => $delivery->received_at?->format('d/m/Y H:i'),
                'items' => $delivery->items->map(fn ($i) => ['description' => $i->orderItem->description, 'unit' => $i->orderItem->unit, 'qty' => $i->qty])->values(),
            ],
            'can' => ['ship' => $delivery->status === 'draft' && $u->can('deliveries.manage'), 'receive' => $delivery->status === 'shipped' && $u->can('deliveries.manage'), 'delete' => $delivery->status === 'draft' && $u->can('deliveries.manage')],
        ]);
    }

    /** Kirim: stok pesanan (delivered_qty) berkurang di sini, dengan kunci baris agar aman dari proses ganda. */
    public function ship(Request $request, DeliveryNote $delivery)
    {
        DB::transaction(function () use ($delivery) {
            $d = DeliveryNote::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            abort_unless($d->status === 'draft', 422, 'Surat jalan ini sudah diproses.');

            $order = SalesOrder::whereKey($d->sales_order_id)->lockForUpdate()->firstOrFail();
            abort_if($order->status === 'cancelled', 422, 'Sales order sudah dibatalkan.');

            foreach ($d->items as $line) {
                $it = SalesOrderItem::whereKey($line->sales_order_item_id)->lockForUpdate()->firstOrFail();
                abort_if((float) $line->qty > (float) $it->qty - (float) $it->delivered_qty + 1e-9, 422, "Sisa pesanan {$it->description} tidak cukup (mungkin sudah dikirim lewat surat jalan lain).");
                $it->update(['delivered_qty' => DB::raw('delivered_qty + '.number_format((float) $line->qty, 3, '.', ''))]);
            }

            $d->update(['status' => 'shipped']);

            $open = $order->items()->whereColumn('delivered_qty', '<', 'qty')->exists();
            $order->update(['status' => $open ? 'partial' : 'delivered']);
        });
        ActivityLogger::log('delivery.shipped', "Surat jalan {$delivery->number} dikirim", $delivery);

        return back()->with('success', 'Barang ditandai dikirim; sisa pesanan diperbarui.');
    }

    public function receive(Request $request, DeliveryNote $delivery)
    {
        $data = $request->validate(['received_by' => 'required|string|max:100']);
        abort_unless($delivery->status === 'shipped', 422, 'Surat jalan belum dikirim.');
        $delivery->update(['status' => 'received', 'received_by' => $data['received_by'], 'received_at' => now()]);
        ActivityLogger::log('delivery.received', "Surat jalan {$delivery->number} diterima {$data['received_by']}", $delivery);

        return back()->with('success', 'Penerimaan barang dicatat.');
    }

    public function destroy(Request $request, DeliveryNote $delivery)
    {
        abort_unless($delivery->status === 'draft', 422, 'Hanya draft yang bisa dihapus.');
        $delivery->delete();
        ActivityLogger::log('delivery.deleted', "Menghapus draft surat jalan {$delivery->number}", $delivery);

        return redirect()->route('deliveries.index')->with('success', 'Draft surat jalan dihapus.');
    }

    public function pdf(Request $request, DeliveryNote $delivery)
    {
        $delivery->load(['order', 'customer', 'items.orderItem']);
        $this->authorizeOrder($request, $delivery->order);

        return Pdf::loadView('pdf.delivery', ['d' => $delivery, 'co' => config('marketing.company')])->setPaper('a4')->download($delivery->number.'.pdf');
    }

    private function authorizeOrder(Request $request, SalesOrder $order): void
    {
        abort_unless($request->user()->can('scope.all') || $order->sales_user_id === $request->user()->id, 403, 'Dokumen ini milik sales lain.');
    }
}
