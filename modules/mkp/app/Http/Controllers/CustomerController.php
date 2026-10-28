<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->orderBy('name')->get()
            ->map(fn ($c) => [...$c->toArray(), 'outstanding' => $c->outstandingBalance()]);

        return Inertia::render('Customers/Index', ['customers' => $customers, 'filters' => $request->only('search')]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:customers,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'npwp' => 'nullable|string|max:30',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        Customer::create($validated);

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'npwp' => 'nullable|string|max:30',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $customer->update($validated);

        return back()->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->invoices()->exists()) {
            return back()->withErrors(['delete' => 'Pelanggan tidak dapat dihapus karena memiliki riwayat invoice.']);
        }
        $customer->delete();

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }
}
