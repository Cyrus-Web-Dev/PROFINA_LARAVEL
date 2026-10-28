<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use App\Exports\ArrayExport;
use App\Models\DisbursementBatch;
use App\Models\PayrollFinding;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\ApprovalService;
use App\Services\AuditLedger;
use App\Services\BankFileService;
use App\Services\FundingService;
use App\Services\PayrollIntegrity;
use App\Services\PayrollRunService;
use App\Services\PayslipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class RunController extends Controller
{
    public function __construct(private PayrollRunService $svc)
    {
        $this->middleware('permission:runs.view')->only(['index', 'show', 'item', 'exportRegister']);
        $this->middleware('permission:runs.create')->only(['store', 'calculate', 'submit']);
        $this->middleware('permission:runs.hold')->only(['hold', 'release']);
        $this->middleware('permission:runs.cancel')->only(['cancel']);
        $this->middleware('permission:runs.disburse')->only(['bankGenerate', 'bankDownload', 'emailPayslips']);
        $this->middleware('permission:runs.reconcile')->only(['bankReconcile']);
    }

    public function index(Request $request)
    {
        $runs = PayrollRun::query()->when($request->status, fn ($q, $s) => $q->where('status', $s))->when($request->year, fn ($q, $y) => $q->where('period_year', $y))
            ->orderByDesc('period_year')->orderByDesc('period_month')->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn ($r) => ['id' => $r->id, 'number' => $r->run_number, 'title' => $r->title, 'type' => $r->type, 'status' => $r->status, 'employees' => $r->employee_count,
                'total_net' => $r->total_net, 'total_employer_cost' => $r->total_employer_cost, 'pay_date' => $r->pay_date->toDateString(), 'locked' => $r->isLocked()]);

        return Inertia::render('Runs/Index', ['runs' => $runs, 'filters' => $request->only('status', 'year'), 'defaults' => ['year' => (int) now()->year, 'month' => (int) now()->month], 'statusLabels' => PayrollRun::STATUS_LABELS]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['type' => 'required|in:regular,thr,bonus,correction', 'period_year' => 'required|integer|between:2024,2100', 'period_month' => 'required|integer|between:1,12',
            'pay_date' => 'nullable|date', 'thr_holiday_date' => 'required_if:type,thr|nullable|date', 'notes' => 'nullable|string|max:500']);
        $run = $this->svc->create($d, $request->user());

        return redirect()->route('runs.show', $run->id)->with('success', 'Putaran dibuat. Klik "Hitung" untuk menghitung.');
    }

    public function show(Request $request, PayrollRun $run, ApprovalService $approvals)
    {
        $u = $request->user();
        $run->load('approvals', 'funding', 'batches', 'remittances');
        $pending = $approvals->pending($run);
        $makers = array_filter([$run->created_by, $run->calculated_by, $run->submitted_by]);
        $integrity = $run->isLocked() || $run->status === 'in_review' || $run->status === 'verified'
            ? Cache::remember('pay.integrity.'.$run->id.'.'.$run->updated_at?->timestamp, 120, fn () => PayrollIntegrity::verify($run)) : [];

        $items = $run->items()->with('employee:id,nip')->when($request->q, fn ($q, $s) => $q->whereRaw("employee_snapshot::text ilike ?", ["%$s%"]))->orderBy('id')->paginate(25, ['*'], 'page')->withQueryString()
            ->through(fn ($i) => ['id' => $i->id, 'nip' => $i->employee_snapshot['nip'] ?? '', 'name' => $i->employee_snapshot['full_name'] ?? '', 'department' => $i->employee_snapshot['department'] ?? '', 'gross' => $i->gross_earnings,
                'bpjs_employee' => $i->bpjs_kes_employee + $i->bpjs_jht_employee + $i->bpjs_jp_employee, 'pph21' => $i->pph21, 'method' => $i->pph21_method, 'other' => $i->loan_deduction + $i->other_deductions,
                'net' => $i->net_pay, 'held' => $i->is_held, 'held_reason' => $i->held_reason, 'status' => $i->status, 'emailed' => $i->payslip_emailed_at !== null, 'email_status' => $i->payslip_email_status]);

        $findings = $run->findings()->with('employee:id,nip,full_name')->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")->orderBy('id')->get()
            ->map(fn ($f) => ['id' => $f->id, 'code' => $f->code, 'severity' => $f->severity, 'blocking' => $f->is_blocking, 'title' => $f->title, 'detail' => $f->detail, 'status' => $f->status,
                'note' => $f->resolution_note, 'employee' => $f->employee?->full_name]);

        return Inertia::render('Runs/Show', [
            'run' => [
                'id' => $run->id, 'number' => $run->run_number, 'title' => $run->title, 'type' => $run->type, 'status' => $run->status, 'period' => $run->periodLabel(),
                'pay_date' => $run->pay_date->toDateString(), 'locked' => $run->isLocked(), 'snapshot_hash' => $run->snapshot_hash, 'dual' => $run->requires_dual_approval,
                'employee_count' => $run->employee_count, 'rejection_note' => $run->rejection_note, 'notes' => $run->notes,
                'totals' => ['gross' => $run->total_gross, 'net' => $run->total_net, 'pph21' => $run->total_pph21, 'bpjs_employee' => $run->total_employee_bpjs, 'bpjs_company' => $run->total_employer_bpjs,
                    'other' => $run->total_other_deductions, 'employer_cost' => $run->total_employer_cost, 'disbursable' => $run->total_disbursable],
                'by_department' => collect($run->totals['by_department'] ?? [])->map(fn ($v, $k) => $v + ['department' => \App\Models\Department::find($k)?->name ?? '(tanpa departemen)'])->values(),
                'budget' => $run->budget_check, 'meta' => ['sdm_error' => $run->calc_meta['sdm_error'] ?? null, 'sdm_synced' => $run->calc_meta['sdm_synced'] ?? false, 'reg_asof' => $run->calc_meta['reg_asof'] ?? null],
                'created_at' => $run->created_at?->format('d/m/Y H:i'), 'calculated_at' => $run->calculated_at?->format('d/m/Y H:i'),
                'steps' => $run->approvals->map(fn ($a) => ['step' => $a->step, 'label' => $a->label, 'status' => $a->status, 'by' => $a->acted_name, 'at' => $a->acted_at?->format('d/m/Y H:i'), 'note' => $a->note]),
            ],
            'items' => $items, 'findings' => $findings, 'integrity' => $integrity,
            'funding' => $run->funding ? ['number' => $run->funding->number, 'status' => $run->funding->status, 'note' => $run->funding->decision_note, 'by' => $run->funding->decided_by_name,
                'finance_ref' => $run->funding->finance_ref, 'disbursed_at' => $run->funding->disbursed_at?->format('d/m/Y H:i'), 'disbursement_ref' => $run->funding->disbursement_ref, 'breakdown' => $run->funding->breakdown] : null,
            'batches' => $run->batches->map(fn ($b) => ['id' => $b->id, 'number' => $b->batch_number, 'items' => $b->item_count, 'total' => $b->total_amount, 'sha256' => $b->file_sha256, 'status' => $b->status, 'recon' => $b->reconciliation]),
            'remittances' => $run->remittances->map(fn ($r) => ['id' => $r->id, 'type' => $r->type, 'amount' => $r->amount, 'due' => $r->due_date->toDateString(), 'status' => $r->status, 'ref' => $r->payment_ref]),
            'can' => [
                'calculate' => $u->can('runs.create') && ! $run->isLocked() && in_array($run->status, ['draft', 'calculated', 'rejected'], true),
                'submit' => $u->can('runs.create') && $run->status === 'calculated' && $run->employee_count > 0,
                'decide' => $pending && $u->can($pending->permission) && ! in_array($u->id, $makers, true) && ! $run->approvals->where('acted_by', $u->id)->count() ? $pending->label : null,
                'resolve' => $u->can('findings.resolve'), 'resolveBudget' => $u->can('budgets.approve'), 'hold' => $u->can('runs.hold') && in_array($run->status, ['approved', 'funding', 'funded', 'disbursing', 'in_review', 'verified', 'calculated'], true),
                'cancel' => $u->can('runs.cancel') && ! in_array($run->status, ['paid', 'closed', 'cancelled'], true),
                'bank' => $u->can('runs.disburse') && in_array($run->status, ['funded', 'disbursing'], true), 'reconcile' => $u->can('runs.reconcile') && in_array($run->status, ['paid', 'disbursing'], true),
                'payslips' => $u->can('runs.disburse') && in_array($run->status, ['paid', 'closed'], true), 'resubmit' => $u->can('runs.create') && in_array($run->funding?->status, ['rejected', 'revision'], true),
            ],
        ]);
    }

    public function item(PayrollItem $item)
    {
        return response()->json(['item' => $item->only(['id', 'lines', 'basic_salary', 'fixed_allowances', 'variable_earnings', 'gross_earnings', 'bpjs_kes_employee', 'bpjs_kes_company', 'bpjs_jht_employee', 'bpjs_jht_company',
            'bpjs_jkk_company', 'bpjs_jkm_company', 'bpjs_jp_employee', 'bpjs_jp_company', 'tax_gross', 'pph21', 'pph21_method', 'ter_category', 'ter_rate', 'tax_detail', 'loan_deduction', 'other_deductions',
            'net_pay', 'employer_cost', 'days_scheduled', 'days_present', 'days_absent', 'days_unpaid_leave', 'proration_factor', 'overtime_hours', 'item_hash', 'employee_snapshot', 'contract_terms_hash'])]);
    }

    public function calculate(PayrollRun $run, Request $request)
    {
        $r = $this->svc->calculate($run, $request->user());

        return back()->with('success', "Dihitung: {$r->employee_count} karyawan. Periksa temuan sebelum diajukan.");
    }

    public function submit(PayrollRun $run, Request $request)
    {
        $this->svc->submit($run, $request->user());

        return back()->with('success', 'Diajukan untuk verifikasi.');
    }

    public function decide(PayrollRun $run, Request $request)
    {
        $d = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'nullable|string|max:1000']);
        $r = $this->svc->decide($run, $request->user(), $d['decision'], $d['note'] ?? null);

        return back()->with('success', $r->isLocked() ? 'Disetujui & dikunci. Pengajuan dana dikirim ke Keuangan.' : ($d['decision'] === 'approved' ? 'Langkah persetujuan dicatat.' : 'Putaran dikembalikan.'));
    }

    public function cancel(PayrollRun $run, Request $request)
    {
        $d = $request->validate(['reason' => 'required|string|min:10|max:500']);
        $this->svc->cancel($run, $request->user(), $d['reason']);

        return redirect()->route('runs.index')->with('success', 'Putaran dibatalkan.');
    }

    public function hold(PayrollItem $item, Request $request)
    {
        $d = $request->validate(['reason' => 'required|string|min:10|max:500']);
        $this->svc->hold($item, $request->user(), $d['reason']);

        return back()->with('success', 'Item ditahan: tidak ikut ditransfer.');
    }

    public function release(PayrollItem $item, Request $request)
    {
        $this->svc->release($item, $request->user());

        return back()->with('success', 'Penahanan dilepas.');
    }

    public function resolveFinding(PayrollFinding $finding, Request $request)
    {
        $u = $request->user();
        $d = $request->validate(['status' => 'required|in:resolved,waived', 'note' => 'required|string|min:15|max:1000']);
        $needs = $finding->code === 'BUDGET_EXCEEDED' ? 'budgets.approve' : 'findings.resolve';
        abort_unless($u->can($needs), 403, 'Kamu tidak berwenang menyelesaikan temuan ini.');
        $run = $finding->run;
        if (config('pay.controls.sod_enforced') && $finding->is_blocking && in_array($u->id, array_filter([$run->created_by, $run->calculated_by]), true)) {
            return back()->with('error', 'Pemisahan tugas: orang yang membuat/menghitung putaran tidak boleh menyelesaikan temuan kritisnya.');
        }
        if ($run->isLocked()) {
            return back()->with('error', 'Putaran sudah dikunci.');
        }
        $finding->update(['status' => $d['status'], 'resolved_by' => $u->id, 'resolved_at' => now(), 'resolution_note' => $d['note']]);
        AuditLedger::record('finding.'.$d['status'], $finding, ['code' => $finding->code, 'note' => $d['note']], $run->run_number, $u->id);

        return back()->with('success', 'Temuan dicatat sebagai '.($d['status'] === 'resolved' ? 'selesai' : 'dikecualikan').'.');
    }

    public function resubmitFunding(PayrollRun $run, Request $request, FundingService $funding)
    {
        $d = $request->validate(['note' => 'nullable|string|max:500']);
        $funding->resubmit($run->funding, $request->user(), $d['note'] ?? null);

        return back()->with('success', 'Pengajuan dana dikirim ulang ke Keuangan.');
    }

    public function bankGenerate(PayrollRun $run, Request $request, BankFileService $bank)
    {
        try {
            $b = $bank->generate($run, $request->user());
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Batch '.$b->batch_number.' dibuat. Hash file dikirim ke Keuangan lewat pengajuan dana.');
    }

    public function bankDownload(DisbursementBatch $batch, BankFileService $bank)
    {
        try {
            $csv = $bank->content($batch);
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }
        AuditLedger::record('bank.downloaded', $batch, ['sha256' => $batch->file_sha256], $batch->batch_number);

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="'.$batch->batch_number.'.csv"']);
    }

    public function bankReconcile(DisbursementBatch $batch, Request $request, BankFileService $bank)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);
        try {
            $r = $bank->reconcile($batch, file_get_contents($request->file('file')->getRealPath()), $request->user());
        } catch (WorkflowException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($r['mismatches'] ? 'error' : 'success', $r['mismatches'] ? count($r['mismatches']).' selisih ditemukan; lihat rincian batch.' : "Rekonsiliasi bersih: {$r['matched']} transfer cocok.");
    }

    public function emailPayslips(PayrollRun $run, PayslipService $slips)
    {
        @set_time_limit(0);
        $r = $slips->emailPending($run, 25);

        return back()->with($r['failed'] ? 'error' : 'success', "Slip terkirim: {$r['sent']}, gagal: {$r['failed']}, tersisa: {$r['remaining']}".($r['remaining'] ? ' (klik lagi untuk melanjutkan).' : '.'));
    }

    public function exportRegister(PayrollRun $run, Request $request)
    {
        abort_unless($request->user()->can('reports.export'), 403);
        $rows = $run->items()->orderBy('id')->get()->map(fn ($i) => [
            $i->employee_snapshot['nip'] ?? '', $i->employee_snapshot['full_name'] ?? '', $i->employee_snapshot['department'] ?? '', $i->basic_salary, $i->fixed_allowances, $i->variable_earnings, $i->gross_earnings,
            $i->bpjs_kes_employee, $i->bpjs_jht_employee, $i->bpjs_jp_employee, $i->pph21, $i->loan_deduction, $i->other_deductions, $i->net_pay,
            $i->bpjs_kes_company, $i->bpjs_jht_company, $i->bpjs_jkk_company, $i->bpjs_jkm_company, $i->bpjs_jp_company, $i->employer_cost, $i->is_held ? 'DITAHAN' : $i->status,
        ])->all();
        AuditLedger::record('report.exported', $run, ['report' => 'register'], $run->run_number);

        return Excel::download(new ArrayExport($rows, ['NIP', 'Nama', 'Departemen', 'Gaji pokok', 'Tunj. tetap', 'Variabel', 'Bruto', 'BPJS Kes (K)', 'JHT (K)', 'JP (K)', 'PPh 21', 'Kasbon', 'Potongan lain', 'Gaji bersih',
            'BPJS Kes (P)', 'JHT (P)', 'JKK (P)', 'JKM (P)', 'JP (P)', 'Biaya perusahaan', 'Status'], 'Register'), 'register-'.$run->run_number.'.xlsx');
    }
}
