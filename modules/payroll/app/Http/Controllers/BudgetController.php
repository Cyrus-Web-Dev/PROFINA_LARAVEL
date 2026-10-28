<?php

namespace App\Http\Controllers;

use App\Models\BudgetLine;
use App\Models\BudgetPlan;
use App\Models\Department;
use App\Services\AuditLedger;
use App\Services\BudgetService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function __construct(private BudgetService $svc)
    {
        $this->middleware('permission:budgets.view')->only(['index', 'show', 'simulate']);
        $this->middleware('permission:budgets.manage')->only(['store', 'saveLines', 'submit', 'newVersion']);
        $this->middleware('permission:budgets.approve')->only(['decide']);
    }

    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        return Inertia::render('Budgets/Index', [
            'year' => $year,
            'plans' => BudgetPlan::where('fiscal_year', $year)->orderByDesc('version')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'version' => $p->version, 'status' => $p->status, 'total' => $p->total_amount, 'pushed' => $p->pushed_to_finance_at?->format('d/m/Y H:i')]),
            'report' => $this->svc->report($year),
            'sim' => $request->filled('pct') ? $this->svc->simulateRaise((float) $request->pct, $request->filled('dept') ? [(int) $request->dept] : null, (int) ($request->from_month ?: now()->month)) : null,
            'simInput' => $request->only('pct', 'dept', 'from_month'),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['year' => 'required|integer|between:2024,2100', 'copy_from' => 'nullable|exists:budget_plans,id']);
        $plan = $this->svc->newVersion($d['year'], $request->user(), isset($d['copy_from']) ? BudgetPlan::find($d['copy_from']) : null);

        return redirect()->route('budgets.show', $plan->id)->with('success', 'Anggaran baru dibuat.');
    }

    public function newVersion(BudgetPlan $plan, Request $request)
    {
        $new = $this->svc->newVersion($plan->fiscal_year, $request->user(), $plan);

        return redirect()->route('budgets.show', $new->id)->with('success', 'Versi baru dibuat dari '.$plan->name.'.');
    }

    public function show(Request $request, BudgetPlan $plan)
    {
        $plan->load('lines', 'approvals');
        $u = $request->user();

        return Inertia::render('Budgets/Show', [
            'plan' => ['id' => $plan->id, 'name' => $plan->name, 'year' => $plan->fiscal_year, 'version' => $plan->version, 'status' => $plan->status, 'total' => $plan->total_amount, 'rejection_note' => $plan->rejection_note,
                'steps' => $plan->approvals->map(fn ($a) => ['label' => $a->label, 'status' => $a->status, 'by' => $a->acted_name, 'at' => $a->acted_at?->format('d/m/Y H:i'), 'note' => $a->note])],
            'lines' => $plan->lines->map(fn ($l) => ['department_id' => $l->department_id, 'month' => $l->month, 'category' => $l->category, 'amount' => $l->amount, 'planned_headcount' => $l->planned_headcount]),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'categories' => BudgetPlan::CATEGORIES,
            'can' => ['edit' => $u->can('budgets.manage') && in_array($plan->status, ['draft', 'rejected'], true), 'submit' => $u->can('budgets.manage') && in_array($plan->status, ['draft', 'rejected'], true),
                'decide' => $u->can('budgets.approve') && $plan->status === 'submitted' && $plan->submitted_by !== $u->id, 'version' => $u->can('budgets.manage') && in_array($plan->status, ['active', 'superseded', 'approved'], true)],
        ]);
    }

    public function saveLines(Request $request, BudgetPlan $plan)
    {
        $d = $request->validate(['lines' => 'required|array', 'lines.*.department_id' => 'required|exists:departments,id', 'lines.*.month' => 'required|integer|between:1,12',
            'lines.*.category' => 'required|in:'.implode(',', array_keys(BudgetPlan::CATEGORIES)), 'lines.*.amount' => 'required|numeric|min:0', 'lines.*.planned_headcount' => 'nullable|integer|min:0']);
        $this->svc->saveLines($plan, $d['lines']);

        return back()->with('success', 'Anggaran tersimpan.');
    }

    public function submit(Request $request, BudgetPlan $plan)
    {
        $this->svc->submit($plan, $request->user());

        return back()->with('success', 'Anggaran diajukan untuk persetujuan.');
    }

    public function decide(Request $request, BudgetPlan $plan)
    {
        $d = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $r = $this->svc->decide($plan, $request->user(), $d['decision'], $d['note'] ?? null);

        return back()->with('success', $r->status === 'active' ? 'Anggaran disetujui & aktif. Dikirim ke Keuangan.' : 'Keputusan dicatat.');
    }
}
