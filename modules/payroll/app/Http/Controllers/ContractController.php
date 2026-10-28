<?php

namespace App\Http\Controllers;

use App\Models\CompensationContract;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\PayComponent;
use App\Services\ContractService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function __construct(private ContractService $svc)
    {
        $this->middleware('permission:contracts.view')->only(['index', 'show']);
        $this->middleware('permission:contracts.propose')->only(['create', 'store']);
    }

    public function index(Request $request)
    {
        $status = $request->status;
        $q = CompensationContract::query()->with(['employee:id,nip,full_name', 'grade:id,code'])
            ->when($status, fn ($x, $s) => $s === 'pending' ? $x->whereIn('status', ['proposed', 'verified']) : $x->where('status', $s))
            ->when($request->search, fn ($x, $s) => $x->whereHas('employee', fn ($e) => $e->where('full_name', 'ilike', "%$s%")->orWhere('nip', 'ilike', "%$s%")))
            ->orderByDesc('id');

        return Inertia::render('Contracts/Index', [
            'contracts' => $q->paginate(20)->withQueryString()->through(fn ($c) => [
                'id' => $c->id, 'number' => $c->contract_number, 'employee' => $c->employee->full_name, 'nip' => $c->employee->nip, 'version' => $c->version,
                'status' => $c->status, 'base_salary' => $c->base_salary, 'effective_date' => $c->effective_date->toDateString(), 'grade' => $c->grade?->code, 'source' => $c->source,
            ]),
            'filters' => $request->only('status', 'search'),
            'counts' => ['pending' => CompensationContract::whereIn('status', ['proposed', 'verified'])->count(), 'active' => CompensationContract::where('status', 'active')->count()],
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Contracts/Form', [
            'employees' => Employee::where('employment_status', 'active')->orderBy('full_name')->get(['id', 'nip', 'full_name']),
            'grades' => Grade::where('is_active', true)->orderBy('min_salary')->get(['id', 'code', 'name', 'min_salary', 'max_salary', 'requires_dual_approval']),
            'components' => PayComponent::where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name', 'type', 'category']),
            'employeeId' => $request->employee ? (int) $request->employee : null,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')],
            'grade_id' => 'nullable|exists:grades,id',
            'contract_type' => 'required|in:pkwt,pkwtt,magang,harian,direksi',
            'base_salary' => 'required|numeric|min:1|max:99999999999',
            'effective_date' => 'required|date',
            'end_date' => 'nullable|date|after:effective_date',
            'jkk_class' => 'required|integer|between:1,5',
            'bpjs_kes_enrolled' => 'boolean', 'bpjs_tk_enrolled' => 'boolean', 'thr_eligible' => 'boolean', 'overtime_eligible' => 'boolean',
            'tax_scheme' => 'required|in:ter,exempt',
            'document_ref' => 'nullable|string|max:120', 'reason' => 'required|string|min:10|max:1000',
            'components' => 'array', 'components.*.pay_component_id' => 'required|exists:pay_components,id|distinct', 'components.*.amount' => 'required|numeric|min:0',
        ]);
        $components = $d['components'] ?? [];
        unset($d['components']);
        $c = $this->svc->propose($d, $components, $request->user());

        return redirect()->route('contracts.show', $c->id)->with('success', 'Usulan kontrak dibuat. Menunggu verifikasi & persetujuan (dua orang lain).');
    }

    public function show(CompensationContract $contract)
    {
        $contract->load('employee.department', 'grade', 'components.component', 'approvals');

        return Inertia::render('Contracts/Show', [
            'contract' => [
                'id' => $contract->id, 'number' => $contract->contract_number, 'version' => $contract->version, 'status' => $contract->status, 'source' => $contract->source,
                'base_salary' => $contract->base_salary, 'effective_date' => $contract->effective_date->toDateString(), 'end_date' => $contract->end_date?->toDateString(),
                'contract_type' => $contract->contract_type, 'grade' => $contract->grade ? $contract->grade->code.' - '.$contract->grade->name : null,
                'jkk_class' => $contract->jkk_class, 'bpjs_kes' => $contract->bpjs_kes_enrolled, 'bpjs_tk' => $contract->bpjs_tk_enrolled, 'tax_scheme' => $contract->tax_scheme,
                'thr' => $contract->thr_eligible, 'overtime' => $contract->overtime_eligible, 'reason' => $contract->reason, 'document_ref' => $contract->document_ref,
                'proposed_by' => $contract->proposed_by_name, 'terms_hash' => $contract->terms_hash, 'intact' => $contract->intact(),
                'rejection_note' => $contract->rejection_note, 'activated_at' => $contract->activated_at?->format('d/m/Y H:i'),
                'in_band' => $contract->grade ? $contract->grade->inBand($contract->base_salary) : null,
                'band' => $contract->grade ? [$contract->grade->min_salary, $contract->grade->max_salary] : null,
                'employee' => ['id' => $contract->employee->id, 'nip' => $contract->employee->nip, 'name' => $contract->employee->full_name, 'department' => $contract->employee->department?->name, 'sdm_base_salary' => $contract->employee->sdm_base_salary],
                'components' => $contract->components->map(fn ($c) => ['name' => $c->component->name, 'type' => $c->component->type, 'amount' => $c->amount]),
                'approvals' => $contract->approvals->map(fn ($a) => ['step' => $a->step, 'label' => $a->label, 'status' => $a->status, 'by' => $a->acted_name, 'at' => $a->acted_at?->format('d/m/Y H:i'), 'note' => $a->note, 'permission' => $a->permission]),
            ],
            'previous' => CompensationContract::where('employee_id', $contract->employee_id)->where('version', '<', $contract->version)->orderByDesc('version')->first(['contract_number', 'base_salary', 'status']),
        ]);
    }

    public function decide(Request $request, CompensationContract $contract)
    {
        $d = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $this->svc->decide($contract, $request->user(), $d['decision'], $d['note'] ?? null);

        return back()->with('success', $d['decision'] === 'approved' ? 'Langkah persetujuan dicatat.' : 'Kontrak ditolak.');
    }
}
