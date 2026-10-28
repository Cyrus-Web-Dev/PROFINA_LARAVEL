<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $vendors = Vendor::when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->orderBy('name')->get()
            ->map(fn ($v) => [...$v->toArray(), 'outstanding' => $v->outstandingBalance()]);

        return Inertia::render('Vendors/Index', ['vendors' => $vendors, 'filters' => $request->only('search')]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:vendors,code',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'npwp' => 'nullable|string|max:30',
        ]);

        Vendor::create($validated);

        return back()->with('success', 'Vendor berhasil ditambahkan.');
    }

    public function update(Request $request, Vendor $vendor)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'npwp' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ]);

        $vendor->update($validated);

        return back()->with('success', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->bills()->exists()) {
            return back()->withErrors(['delete' => 'Vendor tidak dapat dihapus karena memiliki riwayat bill.']);
        }
        $vendor->delete();

        return back()->with('success', 'Vendor berhasil dihapus.');
    }
}
