<?php

namespace App\Http\Controllers;

use App\Exceptions\PayrollException;
use App\Models\ChartOfAccount;
use App\Models\PayrollEvent;
use App\Models\PayrollFunding;
use App\Models\PayrollRemittance;
use App\Services\PayrollClient;
use App\Services\PayrollFundingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Modul "Dana Payroll": keputusan, pencairan gaji, dan setoran pajak/BPJS (terhubung ke PAYBRIDGE lewat REST API). */
class PayrollFundingController extends Controller
{
    public function __construct(private PayrollFundingService $svc)
    {
    }

    public function index(Request $request, PayrollClient $client)
    {
        $tab = $request->get('tab', 'waiting');
        $q = PayrollFunding::query()->orderByDesc('id');
        match ($tab) {
            'waiting' => $q->where('status', 'submitted'),
            'cash' => $q->where('status', 'approved'),
            'done' => $q->whereIn('status', ['disbursed', 'closed']),
            default => null,
        };

        return Inertia::render('Payroll/Index', [
            'tab' => $tab,
            'funds' => $tab === 'remit' ? null : $q->paginate(15)->withQueryString()->through(fn ($f) => $this->row($f)),
            'remittances' => $tab === 'remit' ? PayrollRemittance::orderByRaw("case status when 'pending' then 0 else 1 end")->orderBy('due_date')->paginate(20)->through(fn ($r) => [
                'id' => $r->id, 'type' => $r->type, 'label' => config("payroll.remittance_labels.{$r->type}"), 'run' => $r->run_number, 'period' => sprintf('%02d/%d', $r->period_month, $r->period_year),
                'amount' => $r->amount, 'due' => $r->due_date->format('d/m/Y'), 'overdue' => $r->status === 'pending' && $r->due_date->isPast(), 'status' => $r->status, 'ref' => $r->payment_ref]) : null,
            'counts' => ['waiting' => PayrollFunding::where('status', 'submitted')->count(), 'cash' => PayrollFunding::where('status', 'approved')->count(), 'remit' => PayrollRemittance::where('status', 'pending')->count()],
            'cashAccounts' => ChartOfAccount::cashBank()->orderBy('code')->get(['id', 'code', 'name']),
            'events' => PayrollEvent::orderByDesc('id')->limit(8)->get(['event', 'status', 'note', 'created_at'])->map(fn ($e) => $e->toArray() + ['when' => $e->created_at->format('d/m H:i')]),
            'connected' => $client->configured(), 'lastSync' => PayrollFunding::max('synced_at'),
        ]);
    }

    public function show(PayrollFunding $funding)
    {
        return Inertia::render('Payroll/Show', [
            'funding' => $this->row($funding) + [
                'breakdown' => $funding->breakdown, 'bank_batches' => $funding->bank_batches ?? [], 'snapshot_hash' => $funding->snapshot_hash, 'finance_ref' => $funding->finance_ref,
                'decision_note' => $funding->decision_note, 'decided_by' => $funding->decided_by_name, 'decided_at' => $funding->decided_at?->format('d/m/Y H:i'),
                'disbursed_at' => $funding->disbursed_at?->format('d/m/Y H:i'), 'disbursement_ref' => $funding->disbursement_ref, 'last_error' => $funding->last_error,
                'accrual_journal_id' => $funding->accrual_journal_id, 'disbursement_journal_id' => $funding->disbursement_journal_id,
                'accounts' => collect(array_merge(array_keys($funding->breakdown['debit'] ?? []), array_keys($funding->breakdown['credit'] ?? [])))->unique()->mapWithKeys(fn ($k) => [$k => config("payroll.accounts.$k.1").' ('.config("payroll.accounts.$k.0").')'])->all(),
            ],
            'cashAccounts' => ChartOfAccount::cashBank()->orderBy('code')->get(['id', 'code', 'name']),
            'threshold' => (float) config('payroll.approval_threshold'),
        ]);
    }

    private function row(PayrollFunding $f): array
    {
        return ['id' => $f->id, 'number' => $f->payroll_number, 'run' => $f->run_number, 'title' => $f->title, 'period' => sprintf('%02d/%d', $f->period_month, $f->period_year), 'pay_date' => $f->pay_date?->format('d/m/Y'),
            'status' => $f->status, 'employees' => $f->employee_count, 'net' => $f->amount_net_total, 'disbursable' => $f->amount_disbursable, 'disbursed' => $f->disbursed_amount];
    }

    public function sync(Request $request)
    {
        try {
            $n = $this->svc->syncAll();
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Sinkron PAYBRIDGE selesai: {$n} pengajuan diperbarui.");
    }

    public function refresh(PayrollFunding $funding)
    {
        return $this->svc->syncOne($funding) ? back()->with('success', 'Data dimuat ulang dari PAYBRIDGE.') : back()->with('error', $funding->fresh()->last_error ?: 'Gagal memuat ulang.');
    }

    public function decide(Request $request, PayrollFunding $funding)
    {
        $d = $request->validate(['decision' => 'required|in:approve,reject,revision', 'note' => 'nullable|string|max:1000', 'journal_date' => 'nullable|date']);
        try {
            $this->svc->decide($funding, $d['decision'], $d['note'] ?? null, $d['journal_date'] ?? null, $request->user());
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $d['decision'] === 'approve' ? 'Disetujui & jurnal akrual dibuat.' : 'Keputusan dikirim ke Payroll.');
    }

    public function disburse(Request $request, PayrollFunding $funding)
    {
        $d = $request->validate(['cash_account_id' => 'required|exists:chart_of_accounts,id', 'date' => 'required|date', 'method' => 'required|string|max:20', 'reference' => 'nullable|string|max:80']);
        try {
            $this->svc->disburse($funding, $d, $request->user());
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Gaji dicairkan, jurnal kas dibuat, dan Payroll sudah diberi tahu (slip gaji terbit).');
    }

    public function payRemittance(Request $request, PayrollRemittance $remittance)
    {
        $d = $request->validate(['cash_account_id' => 'required|exists:chart_of_accounts,id', 'date' => 'required|date', 'reference' => 'required|string|max:80']);
        try {
            $this->svc->payRemittance($remittance, $d, $request->user());
        } catch (PayrollException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Setoran dicatat, jurnal dibuat, dan Payroll diberi tahu.');
    }
}
