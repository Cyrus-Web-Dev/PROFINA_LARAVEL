<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $customers = Customer::query()->visibleTo($user)->with(['level:id,name', 'owner:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.$request->q.'%')->orWhere('code', 'ilike', '%'.$request->q.'%')->orWhere('company', 'ilike', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('level'), fn ($q) => $q->where('price_level_id', $request->level))
            ->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (Customer $c) => [
                'id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'company' => $c->company, 'level' => $c->level?->name, 'price_level_id' => $c->price_level_id,
                'email' => $c->email, 'phone' => $c->phone, 'address' => $c->address, 'city' => $c->city, 'npwp' => $c->npwp,
                'payment_terms_days' => $c->payment_terms_days, 'credit_limit' => $c->credit_limit, 'owner_id' => $c->owner_id, 'owner' => $c->owner?->name,
                'status' => $c->status, 'notes' => $c->notes,
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('q', 'status', 'level'),
            'levels' => PriceLevel::orderBy('sort')->get(['id', 'name']),
            'salesUsers' => $user->can('scope.all') ? User::role(['sales', 'sales-admin', 'marketing-manager'])->orderBy('name')->get(['id', 'name']) : [],
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = 'C'.str_pad((string) ((int) Customer::withTrashed()->max('id') + 1), 5, '0', STR_PAD_LEFT);
        // Sales biasa selalu menjadi pemilik pelanggan yang dibuatnya.
        $data['owner_id'] = $request->user()->can('scope.all') ? ($data['owner_id'] ?? $request->user()->id) : $request->user()->id;

        $c = Customer::create($data);
        ActivityLogger::log('customer.created', "Pelanggan baru {$c->code} {$c->name}", $c);

        return back()->with('success', 'Pelanggan ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeOwner($request, $customer);
        $data = $this->validated($request);
        if (! $request->user()->can('scope.all')) {
            unset($data['owner_id']);
        }
        $customer->update($data);
        ActivityLogger::log('customer.updated', "Mengubah pelanggan {$customer->code}", $customer);

        return back()->with('success', 'Pelanggan diperbarui.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorizeOwner($request, $customer);
        abort_if(DB::table('sales_orders')->where('customer_id', $customer->id)->whereNotIn('status', ['delivered', 'cancelled'])->exists(), 422, 'Pelanggan masih punya sales order berjalan.');
        $customer->delete();
        ActivityLogger::log('customer.deleted', "Menghapus pelanggan {$customer->code}", $customer);

        return back()->with('success', 'Pelanggan dihapus (arsip).');
    }

    /** Harga khusus per pelanggan (menimpa harga level). */
    public function prices(Customer $customer)
    {
        return response()->json($customer->specialPrices()->get(['product_id', 'price', 'min_qty']));
    }

    public function syncPrices(Request $request, Customer $customer)
    {
        $this->authorizeOwner($request, $customer);
        $data = $request->validate([
            'prices' => 'array',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.min_qty' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($customer, $data) {
            $customer->specialPrices()->delete();
            foreach (collect($data['prices'] ?? [])->unique('product_id') as $p) {
                $customer->specialPrices()->create(['product_id' => $p['product_id'], 'price' => $p['price'], 'min_qty' => $p['min_qty'] ?? null]);
            }
        });
        ActivityLogger::log('customer.prices', "Mengubah harga khusus {$customer->code}", $customer);

        return back()->with('success', 'Harga khusus pelanggan disimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'company' => 'nullable|string|max:150',
            'price_level_id' => 'required|exists:price_levels,id',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:80',
            'npwp' => 'nullable|string|max:30',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'credit_limit' => 'nullable|numeric|min:0',
            'owner_id' => 'nullable|exists:users,id',
            'status' => ['required', Rule::in(['prospect', 'active', 'inactive'])],
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    private function authorizeOwner(Request $request, Customer $customer): void
    {
        abort_unless($request->user()->can('scope.all') || $customer->owner_id === $request->user()->id, 403, 'Pelanggan ini milik sales lain.');
    }
}
