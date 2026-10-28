<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\PayComponent;
use App\Services\Regulations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Golongan, komponen gaji, dan regulasi (parameter berlaku per tanggal). */
class MasterController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:grades.manage')->only(['gradeSave']);
        $this->middleware('permission:components.manage')->only(['componentSave']);
        $this->middleware('permission:regulations.manage')->only(['regulationPublish']);
    }

    public function masters()
    {
        return Inertia::render('Master/Index', [
            'grades' => Grade::orderBy('min_salary')->get(),
            'components' => PayComponent::orderBy('sort_order')->get(),
        ]);
    }

    public function gradeSave(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|exists:grades,id', 'code' => 'required|string|max:20', 'name' => 'required|string|max:80', 'tier' => 'required|in:staff,supervisor,manager,executive,director',
            'min_salary' => 'required|numeric|min:0', 'max_salary' => 'required|numeric|gte:min_salary', 'requires_dual_approval' => 'boolean', 'is_active' => 'boolean']);
        $id = $d['id'] ?? null;
        unset($d['id']);
        $id ? Grade::findOrFail($id)->update($d) : Grade::create($d);
        \App\Services\AuditLedger::record('grade.saved', null, $d, $d['code']);

        return back()->with('success', 'Golongan disimpan.');
    }

    public function componentSave(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|exists:pay_components,id', 'code' => 'required|alpha_dash|max:30', 'name' => 'required|string|max:100', 'type' => 'required|in:earning,deduction',
            'category' => 'required|in:fixed_allowance,variable_allowance,deduction', 'taxable' => 'boolean', 'bpjs_base' => 'boolean', 'prorated' => 'boolean',
            'gl_key' => 'required|in:allowance_expense,salary_expense,other_deduction_payable,bonus_expense,reimbursement_expense', 'is_active' => 'boolean']);
        $id = $d['id'] ?? null;
        unset($d['id']);
        $id ? PayComponent::findOrFail($id)->update($d) : PayComponent::create($d);
        \App\Services\AuditLedger::record('component.saved', null, $d, $d['code']);

        return back()->with('success', 'Komponen disimpan. Berlaku untuk kontrak BARU; kontrak lama tidak berubah.');
    }

    public function regulations(Regulations $regs)
    {
        $params = DB::table('regulation_parameters')->orderBy('code')->orderByDesc('effective_from')->get()->map(fn ($r) => [
            'id' => $r->id, 'code' => $r->code, 'value' => json_decode($r->value, true), 'effective_from' => substr($r->effective_from, 0, 10), 'source' => $r->source]);
        $ter = DB::table('tax_ter_rates')->orderBy('category')->orderBy('lower_bound')->get()->groupBy('category')->map(fn ($g) => $g->map(fn ($r) => [(float) $r->lower_bound, $r->upper_bound === null ? null : (float) $r->upper_bound, (float) $r->rate])->values());

        return Inertia::render('Master/Regulations', ['params' => $params, 'ter' => $ter, 'today' => now()->toDateString()]);
    }

    public function regulationPublish(Request $request, Regulations $regs)
    {
        $d = $request->validate(['code' => 'required|string|max:60', 'value' => 'required|string', 'effective_from' => 'required|date', 'source' => 'required|string|max:255']);
        $value = json_decode($d['value'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->withErrors(['value' => 'Nilai harus JSON valid (angka, mis. 11086300, atau objek/larik).']);
        }
        $regs->publish($d['code'], $value, $d['effective_from'], $d['source'], $request->user());

        return back()->with('success', 'Versi parameter baru dicatat (versi lama tetap tersimpan).');
    }
}
