<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function home(Request $request)
    {
        return redirect()->route($request->user() ? 'dashboard' : 'login');
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $monthStart = now()->startOfMonth()->toDateString();

        $orders = SalesOrder::query()->visibleTo($u)->where('status', '!=', 'cancelled');
        $omzetMonth = (clone $orders)->where('order_date', '>=', $monthStart)->sum('total');

        $series = (clone $orders)->where('order_date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->selectRaw("to_char(order_date, 'YYYY-MM') as ym, sum(total) as total, count(*) as n")->groupBy('ym')->orderBy('ym')->get()
            ->map(fn ($r) => ['month' => $r->ym, 'total' => (float) $r->total, 'count' => (int) $r->n]);

        $top = (clone $orders)->where('order_date', '>=', now()->subMonths(5)->startOfMonth()->toDateString())
            ->select('customer_id', DB::raw('sum(total) as total'))->groupBy('customer_id')->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => ['customer' => Customer::withTrashed()->find($r->customer_id)?->name, 'total' => (float) $r->total]);

        $payload = [
            'stats' => [
                'customers' => Customer::visibleTo($u)->where('status', 'active')->count(),
                'quotations_open' => Quotation::visibleTo($u)->whereIn('status', ['draft', 'sent'])->count(),
                'orders_running' => SalesOrder::visibleTo($u)->whereIn('status', ['confirmed', 'in_production', 'ready', 'partial'])->count(),
                'complaints_open' => Complaint::query()->whereIn('status', ['open', 'in_progress'])
                    ->when(! $u->can('scope.all'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('owner_id', $u->id)))->count(),
                'omzet_month' => (float) $omzetMonth,
            ],
            'series' => $series,
            'top' => $top,
            'pendingApproval' => $u->can('quotations.approve') ? Quotation::where('status', 'sent')->with('customer:id,name')->latest('sent_at')->limit(6)->get()->map(fn ($x) => ['id' => $x->id, 'number' => $x->number, 'customer' => $x->customer?->name, 'total' => $x->total]) : [],
        ];

        // Margin bulan ini: rahasia dapur, hanya cost.view.
        if ($u->can('cost.view')) {
            $row = DB::table('sales_order_items as i')->join('sales_orders as o', 'o.id', '=', 'i.sales_order_id')->leftJoin('products as p', 'p.id', '=', 'i.product_id')
                ->where('o.status', '!=', 'cancelled')->where('o.order_date', '>=', $monthStart)
                ->selectRaw('coalesce(sum(i.line_total),0) as revenue, coalesce(sum(coalesce(p.cost_price,0) * i.qty),0) as cost')->first();
            $payload['margin'] = ['revenue' => (float) $row->revenue, 'cost' => (float) $row->cost, 'profit' => (float) $row->revenue - (float) $row->cost];
        }

        return Inertia::render('Dashboard/Index', $payload);
    }

    /** Harga & MOQ yang berlaku untuk pelanggan+produk (dipakai form penawaran). */
    public function priceQuote(Request $request, \App\Services\PriceResolver $resolver)
    {
        $data = $request->validate(['customer_id' => 'required|integer', 'product_id' => 'required|integer']);
        $customer = Customer::visibleTo($request->user())->with('level')->findOrFail($data['customer_id']);
        $product = \App\Models\Product::where('is_active', true)->findOrFail($data['product_id']);

        return response()->json($resolver->resolve($customer, $product) + ['unit' => $product->unit, 'name' => $product->name]);
    }
}
