<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = ChartOfAccount::with('parent')
            // dikelompokkan dengan kurung: (name LIKE .. OR code LIKE ..) AND type = ..  (versi lama salah prioritas OR/AND)
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('code')
            ->get();

        return Inertia::render('ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:chart_of_accounts,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:aset,kewajiban,ekuitas,pendapatan,beban',
            'category' => 'nullable|string|max:50',
            'normal_balance' => 'required|in:debit,kredit',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'is_header' => 'boolean',
            'is_cash_bank' => 'boolean',
            'description' => 'nullable|string',
        ]);

        ChartOfAccount::create($validated);

        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_cash_bank' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $chartOfAccount->update($validated);

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(ChartOfAccount $chartOfAccount)
    {
        if ($chartOfAccount->journalDetails()->exists()) {
            return back()->withErrors(['delete' => 'Akun tidak dapat dihapus karena sudah memiliki transaksi jurnal.']);
        }

        $chartOfAccount->delete();

        return back()->with('success', 'Akun berhasil dihapus.');
    }
}
