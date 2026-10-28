<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\BudgetPlan;
use App\Models\CompensationContract;
use App\Models\Employee;
use App\Models\FundingRequest;
use App\Models\PayrollInput;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Remittance;
use App\Models\WebhookDelivery;
use App\Services\SdmClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, SdmClient $sdm)
    {
        $u = $request->user();
        $staff = $u->can('runs.view') || $u->can('contracts.view') || $u->can('budgets.view') || $u->can('audit.view');
        if (! $staff) {
            return redirect()->route('payslips.mine');
        }

        $tasks = [];
        $pend = Approval::where('status', 'pending')->orderBy('step')->get()->groupBy(fn ($a) => $a->approvable_type.'#'.$a->approvable_id)->map->first();
        foreach ($pend as $a) {
            if (! $u->can($a->permission)) {
                continue;
            }
            $tasks[] = match ($a->approvable_type) {
                'CompensationContract' => ($c = CompensationContract::with('employee:id,full_name')->find($a->approvable_id)) ? ['kind' => 'Kontrak', 'title' => $c->contract_number.' · '.$c->employee->full_name, 'step' => $a->label, 'url' => route('contracts.show', $c->id)] : null,
                'PayrollRun' => ($r = PayrollRun::find($a->approvable_id)) ? ['kind' => 'Payroll', 'title' => $r->run_number.' · '.$r->title, 'step' => $a->label, 'url' => route('runs.show', $r->id)] : null,
                'BudgetPlan' => ($b = BudgetPlan::find($a->approvable_id)) ? ['kind' => 'Anggaran', 'title' => $b->name, 'step' => $a->label, 'url' => route('budgets.show', $b->id)] : null,
                default => null,
            };
        }
        $tasks = array_values(array_filter($tasks));
        if ($u->can('inputs.approve')) {
            $n = PayrollInput::where('status', 'pending')->count();
            $n && $tasks[] = ['kind' => 'Input', 'title' => "$n input variabel menunggu persetujuan", 'step' => 'Setujui/tolak', 'url' => route('inputs.index', ['status' => 'pending'])];
        }

        $lastRun = PayrollRun::where('type', 'regular')->whereNotIn('status', ['cancelled', 'rejected'])->orderByDesc('period_year')->orderByDesc('period_month')->first();
        $trend = PayrollRun::where('type', 'regular')->whereNotNull('locked_at')->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderByDesc('period_year')->orderByDesc('period_month')->limit(12)->get()->reverse()->values()
            ->map(fn ($r) => ['label' => sprintf('%02d/%d', $r->period_month, $r->period_year), 'cost' => $r->total_employer_cost, 'net' => $r->total_net]);

        $employees = Employee::where('employment_status', 'active');

        return Inertia::render('Dashboard/Index', [
            'tasks' => $tasks,
            'kpi' => [
                'active_employees' => (clone $employees)->count(),
                'without_contract' => (clone $employees)->whereDoesntHave('activeContract')->count(),
                'open_runs' => PayrollRun::whereNotIn('status', ['closed', 'cancelled', 'rejected'])->count(),
                'pending_contracts' => CompensationContract::whereIn('status', ['proposed', 'verified'])->count(),
                'remittances_due' => Remittance::where('status', 'pending')->whereDate('due_date', '<=', now()->addDays(5))->count(),
                'webhook_failed' => WebhookDelivery::whereIn('status', ['pending', 'failed'])->where('attempts', '>', 0)->count(),
                'funding_waiting' => FundingRequest::whereIn('status', ['submitted', 'approved'])->count(),
            ],
            'lastRun' => $lastRun ? [
                'id' => $lastRun->id, 'number' => $lastRun->run_number, 'title' => $lastRun->title, 'status' => $lastRun->status,
                'total_net' => $lastRun->total_net, 'total_gross' => $lastRun->total_gross, 'total_pph21' => $lastRun->total_pph21,
                'total_employer_cost' => $lastRun->total_employer_cost, 'employee_count' => $lastRun->employee_count,
            ] : null,
            'trend' => $trend,
            'sdmConfigured' => $sdm->configured(),
            'audit' => Cache::get('pay.audit.last'),
        ]);
    }
}
