<?php

namespace App\Http\Controllers;

use App\Models\PriceLevel;
use App\Models\Product;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $canCost = $request->user()->can('cost.view');

        $products = Product::query()->with('prices')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.$request->q.'%')->orWhere('sku', 'ilike', '%'.$request->q.'%')))
            ->orderBy('name')->paginate(15)->withQueryString()
            ->through(function (Product $p) use ($canCost) {
                $row = [
                    'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'unit' => $p->unit, 'description' => $p->description,
                    'base_price' => $p->base_price, 'min_order_qty' => $p->min_order_qty, 'is_active' => $p->is_active,
                    'prices' => $p->prices->map(fn ($x) => ['price_level_id' => $x->price_level_id, 'price' => $x->price, 'min_qty' => $x->min_qty])->values(),
                ];
                if ($canCost) {
                    $row['cost_price'] = $p->getRawOriginal('cost_price'); // HPP: hanya pemegang izin cost.view
                }

                return $row;
            });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only('q'),
            'levels' => PriceLevel::orderBy('sort')->get(['id', 'code', 'name', 'discount_pct']),
            'canCost' => $canCost,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $p = DB::transaction(function () use ($data) {
            $p = Product::create($data);
            $this->syncPrices($p, $data['prices'] ?? []);

            return $p;
        });
        ActivityLogger::log('product.created', "Produk baru {$p->sku} {$p->name}", $p);

        return back()->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        DB::transaction(function () use ($product, $data, $request) {
            $product->update($data);
            if ($request->user()->can('prices.manage')) {
                $this->syncPrices($product, $data['prices'] ?? []);
            }
        });
        ActivityLogger::log('product.updated', "Mengubah produk {$product->sku}", $product);

        return back()->with('success', 'Produk diperbarui.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        ActivityLogger::log('product.deleted', "Menghapus produk {$product->sku}", $product);

        return back()->with('success', 'Produk dihapus (arsip). Dokumen lama tetap utuh.');
    }

    private function validated(Request $request, ?Product $product): array
    {
        $data = $request->validate([
            'sku' => 'required|string|max:40|unique:products,sku'.($product ? ','.$product->id : ''),
            'name' => 'required|string|max:180',
            'unit' => 'required|string|max:20',
            'description' => 'nullable|string|max:2000',
            'base_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'min_order_qty' => 'required|numeric|min:0.001',
            'is_active' => 'boolean',
            'prices' => 'array',
            'prices.*.price_level_id' => 'required|exists:price_levels,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.min_qty' => 'nullable|numeric|min:0',
        ]);

        // HPP: tanpa izin cost.view, nilainya tidak boleh diubah (dan tidak pernah dikirim ke browser).
        if (! $request->user()->can('cost.view')) {
            unset($data['cost_price']);
        }
        // Harga level hanya boleh diubah pemegang prices.manage.
        if (! $request->user()->can('prices.manage')) {
            unset($data['prices']);
        }

        return $data;
    }

    private function syncPrices(Product $product, array $prices): void
    {
        $product->prices()->delete();
        foreach (collect($prices)->unique('price_level_id') as $p) {
            $product->prices()->create(['price_level_id' => $p['price_level_id'], 'price' => $p['price'], 'min_qty' => $p['min_qty'] ?? null]);
        }
    }
}
