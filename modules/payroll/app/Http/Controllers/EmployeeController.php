<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Services\AuditLedger;
use App\Services\SdmClient;
use App\Services\SdmSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:employees.view')->only(['index', 'show']);
        $this->middleware('permission:sync.run')->only(['sync']);
    }

    public function index(Request $request)
    {
        $q = Employee::query()->with(['department:id,name', 'activeContract:id,employee_id,contract_number,base_salary,effective_date,end_date'])
            ->when($request->search, fn ($x, $s) => $x->where(fn ($w) => $w->where('full_name', 'ilike', "%$s%")->orWhere('nip', 'ilike', "%$s%")))
            ->when($request->department, fn ($x, $d) => $x->where('department_id', $d))
            ->when($request->status, fn ($x, $s) => $x->where('employment_status', $s))
            ->when($request->contract === 'none', fn ($x) => $x->whereDoesntHave('activeContract'))
            ->orderBy('full_name');

        $canSalary = $request->user()->can('contracts.view');

        return Inertia::render('Employees/Index', [
            'employees' => $q->paginate(20)->withQueryString()->through(fn ($e) => [
                'id' => $e->id, 'nip' => $e->nip, 'full_name' => $e->full_name, 'department' => $e->department?->name, 'position' => $e->position_title,
                'status' => $e->employment_status, 'type' => $e->employment_type, 'has_npwp' => $e->has_npwp, 'ptkp' => $e->ptkp_status,
                'contract' => $e->activeContract ? ['number' => $e->activeContract->contract_number, 'base_salary' => $canSalary ? $e->activeContract->base_salary : null, 'end_date' => $e->activeContract->end_date?->toDateString()] : null,
                'synced_at' => $e->synced_at?->format('d/m/Y H:i'),
            ]),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only('search', 'department', 'status', 'contract'),
            'sdmConfigured' => app(SdmClient::class)->configured(),
            'lastSync' => Employee::max('synced_at'),
        ]);
    }

    public function show(Request $request, Employee $employee)
    {
        $employee->load('department', 'contracts.grade');
        $canSalary = $request->user()->can('contracts.view');

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id' => $employee->id, 'nip' => $employee->nip, 'full_name' => $employee->full_name, 'email' => $employee->email, 'department' => $employee->department?->name,
                'position' => $employee->position_title, 'employment_type' => $employee->employment_type, 'status' => $employee->employment_status,
                'join_date' => $employee->join_date?->toDateString(), 'resign_date' => $employee->resign_date?->toDateString(), 'ptkp' => $employee->ptkp_status, 'has_npwp' => $employee->has_npwp,
                'bank' => $employee->bank_name ? $employee->bank_name.' '.Employee::maskAccount($employee->bank_account_number) : null,
                'bank_changed_at' => $employee->bank_changed_at?->format('d/m/Y H:i'), 'synced_at' => $employee->synced_at?->format('d/m/Y H:i'),
                'sdm_base_salary' => $canSalary ? $employee->sdm_base_salary : null,
            ],
            'contracts' => $canSalary ? $employee->contracts->map(fn ($c) => [
                'id' => $c->id, 'number' => $c->contract_number, 'version' => $c->version, 'status' => $c->status, 'base_salary' => $c->base_salary,
                'effective_date' => $c->effective_date->toDateString(), 'end_date' => $c->end_date?->toDateString(), 'grade' => $c->grade?->code, 'source' => $c->source,
            ]) : [],
            'payslips' => $canSalary ? PayrollItem::where('employee_id', $employee->id)->whereHas('run', fn ($r) => $r->whereNotNull('locked_at'))->with('run:id,run_number,title,period_month,period_year,status')
                ->orderByDesc('id')->limit(12)->get()->map(fn ($i) => ['id' => $i->id, 'run' => $i->run->title, 'run_id' => $i->run->id, 'gross' => $i->gross_earnings, 'net' => $i->net_pay, 'pph21' => $i->pph21]) : [],
        ]);
    }

    public function sync(SdmSyncService $sync)
    {
        try {
            $r = $sync->syncEmployees();
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLedger::record('sdm.sync', null, $r, 'Sinkron karyawan');

        return back()->with('success', "Sinkron SDM selesai: {$r['created']} baru, {$r['updated']} berubah, {$r['unchanged']} tetap.");
    }
}
