<?php

namespace App\Services;

use App\Exceptions\IntegrationException;
use App\Models\AttendanceSnapshot;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanRepayment;
use App\Models\OpeningBalance;
use App\Models\PayrollInput;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Calc\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunService
{
    public function __construct(
        private PayrollCalculator $calc,
        private Regulations $regs,
        private FindingsEngine $findings,
        private BudgetService $budgets,
        private ApprovalService $approvals,
        private FundingService $funding,
        private RemittanceService $remittances,
        private SdmSyncService $sync,
        private SdmClient $sdm,
    ) {
    }

    private const PREFIX = ['regular' => 'PR', 'thr' => 'THR', 'bonus' => 'BON', 'final' => 'FIN', 'correction' => 'COR'];

    public function create(array $d, User $by): PayrollRun
    {
        $y = (int) $d['period_year'];
        $m = (int) $d['period_month'];
        $type = $d['type'] ?? 'regular';
        if ($type === 'thr' && empty($d['thr_holiday_date'])) {
            throw ValidationException::withMessages(['thr_holiday_date' => 'Tanggal hari raya wajib diisi untuk putaran THR.']);
        }
        $start = Carbon::create($y, $m, 1)->startOfDay();
        $prefix = self::PREFIX[$type].'-'.$start->format('ym');
        $seq = PayrollRun::where('run_number', 'like', $prefix.'-%')->count() + 1;

        $run = PayrollRun::create([
            'run_number' => $prefix.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT), 'type' => $type, 'period_year' => $y, 'period_month' => $m,
            'period_start' => $start, 'period_end' => $start->copy()->endOfMonth()->startOfDay(),
            'pay_date' => $d['pay_date'] ?? $start->copy()->endOfMonth()->startOfDay(), 'title' => $d['title'] ?? (PayrollRun::TYPE_LABELS[$type].' '.$start->locale('id')->translatedFormat('F Y')),
            'thr_holiday_date' => $d['thr_holiday_date'] ?? null, 'status' => 'draft', 'created_by' => $by->id, 'notes' => $d['notes'] ?? null,
        ]);
        AuditLedger::record('run.created', $run, ['type' => $type, 'period' => "$y-$m"], $run->run_number, $by->id);

        return $run;
    }

    public function calculate(PayrollRun $run, User $by): PayrollRun
    {
        if ($run->isLocked() || ! in_array($run->status, ['draft', 'calculated', 'rejected'], true)) {
            throw ValidationException::withMessages(['run' => 'Putaran ini sudah dikirim/terkunci dan tidak bisa dihitung ulang.']);
        }

        $meta = ['sdm_synced' => false, 'sdm_error' => null];
        if ($run->type === 'regular' && $this->sdm->configured()) {
            try {
                $meta['sdm_employees'] = $this->sync->syncEmployees();
                $meta['sdm_attendance_rows'] = $this->sync->syncAttendance((int) $run->period_year, (int) $run->period_month);
                $meta['sdm_synced'] = true;
            } catch (IntegrationException $e) {
                $meta['sdm_error'] = $e->getMessage();
            }
        }

        return DB::transaction(function () use ($run, $by, $meta) {
            PayrollInput::where('applied_run_id', $run->id)->update(['status' => 'approved', 'applied_run_id' => null]);
            $run->items()->delete();

            $reg = $this->regs->snapshot($run->period_end->toDateString());
            $year = (int) $run->period_year;
            $month = (int) $run->period_month;

            $agg = $this->aggregates($run);
            $employees = $this->employeesFor($run);
            $extra = ['no_contract' => [], 'no_attendance' => [], 'tampered' => [], 'sdm_configured' => $this->sdm->configured()];
            $created = 0;

            foreach ($employees as $e) {
                $c = $e->contractOn($run->period_end->toDateString()) ?? $e->contractOn($run->period_start->toDateString());
                if (! $c) {
                    $extra['no_contract'][] = $e;
                    continue;
                }
                $c->load('components.component', 'grade');
                if (! $c->intact()) {
                    $extra['tampered'][] = $e;
                    continue;
                }

                $att = AttendanceSnapshot::where('employee_id', $e->id)->where('year', $year)->where('month', $month)->first();
                if ($run->type === 'regular' && ! $att) {
                    $extra['no_attendance'][] = $e;
                }

                $inputs = PayrollInput::where('employee_id', $e->id)->where('period_year', $year)->where('period_month', $month)
                    ->where('status', 'approved')->whereNull('applied_run_id')->with('component')->get();
                $inputs = $run->type === 'regular' ? $inputs : $inputs->reject(fn ($x) => $x->type === 'overtime');
                if ($run->type === 'regular' && ! $c->overtime_eligible) {
                    $inputs = $inputs->reject(fn ($x) => $x->type === 'overtime');
                }

                $ctx = [
                    'attendance' => $att, 'inputs' => $inputs, 'loans' => $run->type === 'regular' ? $this->loansDue($e, $year, $month) : [],
                    'ytd' => $agg['ytd'][$e->id] ?? ['gross' => 0, 'pension' => 0, 'tax' => 0],
                    'same_month' => $agg['same'][$e->id] ?? ['gross' => 0, 'tax' => 0],
                    'final_period' => $month === 12 || ($e->resign_date && $e->resign_date->between($run->period_start, $run->period_end)),
                ];

                $res = match ($run->type) {
                    'thr' => $this->calc->thr($run, $e, $c, $reg, $ctx),
                    'bonus', 'correction' => $this->calc->extra($run, $e, $c, $reg, $ctx),
                    default => $this->calc->regular($run, $e, $c, $reg, $ctx),
                };
                if (! $res) {
                    continue;
                }
                if ($res['proration_factor'] <= 0 && $run->type === 'regular') {
                    continue;
                }

                $loanApplied = $res['loan_applied'];
                unset($res['loan_applied']);
                $res['tax_detail'] = ($res['tax_detail'] ?? []) + ['loan_applied' => $loanApplied];

                $item = new PayrollItem($res + [
                    'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'contract_id' => $c->id, 'contract_terms_hash' => $c->terms_hash,
                    'department_id' => $e->department_id, 'grade_code' => $c->grade?->code,
                    'employee_snapshot' => [
                        'nip' => $e->nip, 'full_name' => $e->full_name, 'department' => $e->department?->name, 'position' => $e->position_title,
                        'ptkp' => $e->ptkp_status, 'has_npwp' => (bool) $e->has_npwp, 'bank_name' => $e->bank_name,
                        'bank_masked' => Employee::maskAccount($e->bank_account_number), 'bank_account_name' => $e->bank_account_name, 'email' => $e->email,
                    ],
                    'bank_account_hash' => $e->bank_account_hash, 'item_hash' => str_repeat('0', 64), 'status' => 'calculated',
                ]);
                $item->save();
                $item->update(['item_hash' => $item->computeHash()]);

                foreach ($inputs as $in) {
                    $consumed = $in->type !== 'overtime' || $c->overtime_eligible;
                    if ($consumed) {
                        if ($in->type === 'overtime') {
                            $hit = collect($res['lines'])->first(fn ($l) => $l['code'] === 'LEMBUR' && str_starts_with($l['name'], 'Lembur '.$in->work_date?->format('d/m').' '));
                            $in->amount = $hit['amount'] ?? $in->amount;
                        }
                        $in->update(['status' => 'applied', 'applied_run_id' => $run->id, 'amount' => $in->amount]);
                    }
                }
                $created++;
            }

            $this->refreshTotals($run, $reg);
            $run->refresh();
            $budget = $this->budgets->checkRun($run);
            $meta['reg_asof'] = $reg->asOf;
            $meta['reg_values'] = $reg->dump();
            $run->update(['status' => 'calculated', 'calculated_by' => $by->id, 'calculated_at' => now(), 'budget_check' => $budget, 'calc_meta' => $meta,
                'rejection_note' => null, 'verified_by' => null, 'verified_at' => null, 'approved_by' => null, 'approved_at' => null, 'second_approved_by' => null, 'second_approved_at' => null]);

            $this->findings->scan($run->fresh(), $extra, $budget);
            AuditLedger::record('run.calculated', $run, ['employees' => $created, 'total_net' => $run->total_net, 'total_gross' => $run->total_gross, 'reg_asof' => $reg->asOf], $run->run_number, $by->id);

            return $run->fresh();
        });
    }

    public function submit(PayrollRun $run, User $by): PayrollRun
    {
        if ($run->status !== 'calculated' || $run->employee_count === 0) {
            throw ValidationException::withMessages(['run' => 'Hanya putaran yang sudah dihitung dan berisi karyawan yang bisa diajukan.']);
        }
        $dual = $run->total_net >= (float) config('pay.controls.dual_approval_threshold')
            || $run->items()->whereIn('grade_code', \App\Models\Grade::where('requires_dual_approval', true)->pluck('code'))->exists();

        $steps = [['runs.verify', 'Verifikasi Manager Payroll'], ['runs.approve', 'Persetujuan Keuangan']];
        if ($dual) {
            $steps[] = ['runs.approve-executive', 'Persetujuan Direksi'];
        }
        DB::transaction(function () use ($run, $by, $dual, $steps) {
            $run->update(['status' => 'in_review', 'submitted_by' => $by->id, 'submitted_at' => now(), 'requires_dual_approval' => $dual]);
            $this->approvals->start($run, $steps);
            AuditLedger::record('run.submitted', $run, ['total_net' => $run->total_net, 'dual' => $dual], $run->run_number, $by->id);
        });

        return $run->fresh();
    }

    public function decide(PayrollRun $run, User $user, string $decision, ?string $note): PayrollRun
    {
        if (! in_array($run->status, ['in_review', 'verified'], true)) {
            throw ValidationException::withMessages(['run' => 'Putaran tidak sedang menunggu keputusan.']);
        }
        if (config('pay.controls.sod_enforced') && in_array($user->id, array_filter([$run->created_by, $run->calculated_by, $run->submitted_by]), true)) {
            throw ValidationException::withMessages(['approval' => 'Pemisahan tugas: orang yang membuat/menghitung/mengajukan putaran tidak boleh memverifikasi atau menyetujuinya.']);
        }

        return DB::transaction(function () use ($run, $user, $decision, $note) {
            $step = $this->approvals->pending($run);

            if ($decision === 'approved') {
                $open = FindingsEngine::blockingOpen($run);
                if ($open->isNotEmpty()) {
                    throw ValidationException::withMessages(['approval' => $open->count().' temuan kritis belum diselesaikan. Selesaikan/jelaskan semuanya dulu.']);
                }
                $errors = PayrollIntegrity::verify($run);
                if ($errors) {
                    throw ValidationException::withMessages(['approval' => 'Verifikasi integritas gagal: '.implode(' ', array_slice($errors, 0, 3))]);
                }
            }

            $res = $this->approvals->act($run, $user, $decision, $note, null);

            if ($decision === 'rejected') {
                $run->update(['status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_note' => $note]);
                AuditLedger::record('run.rejected', $run, ['step' => $step->label, 'note' => $note], $run->run_number, $user->id);

                return $run->fresh();
            }

            match ($res['approval']->step) {
                1 => $run->update(['status' => 'verified', 'verified_by' => $user->id, 'verified_at' => now(), 'verification_note' => $note]),
                2 => $run->update(['approved_by' => $user->id, 'approved_at' => now()]),
                default => $run->update(['second_approved_by' => $user->id, 'second_approved_at' => now()]),
            };

            if ($res['complete']) {
                $this->lock($run->fresh(), $user);
            }

            return $run->fresh();
        });
    }

    /** Kunci putaran: hash snapshot, terapkan kasbon, buat kewajiban setoran, ajukan dana ke Keuangan. */
    private function lock(PayrollRun $run, User $by): void
    {
        $hash = PayrollIntegrity::snapshotHash($run);
        $run->update(['status' => 'approved', 'locked_at' => now(), 'snapshot_hash' => $hash]);

        foreach ($run->items()->get() as $i) {
            foreach ($i->tax_detail['loan_applied'] ?? [] as $la) {
                $loan = EmployeeLoan::lockForUpdate()->find($la['id']);
                if (! $loan) {
                    continue;
                }
                LoanRepayment::firstOrCreate(['loan_id' => $loan->id, 'payroll_run_id' => $run->id], ['amount' => $la['amount']]);
                $remaining = max(0, $loan->remaining - $la['amount']);
                $loan->update(['remaining' => $remaining, 'paid_installments' => $loan->paid_installments + 1, 'status' => $remaining <= 0.005 ? 'settled' : 'active']);
            }
        }

        AuditLedger::record('run.locked', $run, ['snapshot_hash' => $hash, 'total_net' => $run->total_net, 'total_gross' => $run->total_gross, 'total_pph21' => $run->total_pph21, 'employees' => $run->employee_count], $run->run_number, $by->id);

        $this->remittances->createFor($run);
        $this->funding->submit($run->fresh(), $by);
    }

    public function cancel(PayrollRun $run, User $by, string $reason): void
    {
        if (in_array($run->status, ['paid', 'closed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['run' => 'Putaran yang sudah dibayar/ditutup tidak bisa dibatalkan. Gunakan putaran koreksi.']);
        }
        DB::transaction(function () use ($run, $by, $reason) {
            PayrollInput::where('applied_run_id', $run->id)->update(['status' => 'approved', 'applied_run_id' => null]);
            if ($run->isLocked()) {
                // kembalikan efek kasbon
                foreach (LoanRepayment::where('payroll_run_id', $run->id)->get() as $r) {
                    $loan = EmployeeLoan::find($r->loan_id);
                    $loan?->update(['remaining' => $loan->remaining + $r->amount, 'paid_installments' => max(0, $loan->paid_installments - 1), 'status' => 'active']);
                    $r->delete();
                }
                $run->remittances()->where('status', 'pending')->delete();
                $this->funding->cancel($run, $reason);
            }
            $run->update(['status' => 'cancelled', 'cancelled_by' => $by->id, 'cancelled_at' => now(), 'rejection_note' => $reason]);
            AuditLedger::record('run.cancelled', $run, ['reason' => $reason], $run->run_number, $by->id);
        });
    }

    public function hold(PayrollItem $item, User $by, string $reason): void
    {
        if (strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Alasan penahanan minimal 10 karakter.']);
        }
        if (in_array($item->run->status, ['paid', 'closed', 'cancelled'], true) || $item->status === 'paid') {
            throw ValidationException::withMessages(['item' => 'Item ini sudah dibayar atau putarannya sudah selesai.']);
        }
        $item->update(['is_held' => true, 'held_reason' => $reason, 'held_by' => $by->id, 'held_at' => now()]);
        $this->recomputeDisbursable($item->run);
        AuditLedger::record('item.held', $item, ['nip' => $item->employee_snapshot['nip'] ?? null, 'reason' => $reason, 'net' => $item->net_pay], $item->run->run_number, $by->id);
    }

    public function release(PayrollItem $item, User $by): void
    {
        $item->update(['is_held' => false, 'held_reason' => null, 'held_by' => null, 'held_at' => null]);
        $this->recomputeDisbursable($item->run);
        AuditLedger::record('item.released', $item, ['nip' => $item->employee_snapshot['nip'] ?? null], $item->run->run_number, $by->id);
    }

    public function recomputeDisbursable(PayrollRun $run): void
    {
        $run->update(['total_disbursable' => (float) $run->items()->where('is_held', false)->sum('net_pay')]);
        $run->funding?->update(['amount_disbursable' => $run->fresh()->total_disbursable]);
    }

    /** Muat ulang total & rincian per kunci buku besar/departemen dari item. */
    public function refreshTotals(PayrollRun $run, $reg = null): void
    {
        $items = $run->items()->get();
        $byGl = [];
        $byDept = [];
        foreach ($items as $i) {
            foreach ($i->lines as $l) {
                $byGl[$l['gl']] = ($byGl[$l['gl']] ?? 0) + (($l['type'] === 'earning') ? $l['amount'] : 0);
            }
            $d = (int) $i->department_id;
            $byDept[$d]['count'] = ($byDept[$d]['count'] ?? 0) + 1;
            $byDept[$d]['gross'] = ($byDept[$d]['gross'] ?? 0) + $i->gross_earnings;
            $byDept[$d]['net'] = ($byDept[$d]['net'] ?? 0) + $i->net_pay;
            $byDept[$d]['employer_cost'] = ($byDept[$d]['employer_cost'] ?? 0) + $i->employer_cost;
        }
        $sum = fn (string $col) => (float) $items->sum($col);
        $bpjsEe = $sum('bpjs_kes_employee') + $sum('bpjs_jht_employee') + $sum('bpjs_jp_employee');
        $bpjsEr = $sum('bpjs_kes_company') + $sum('bpjs_jht_company') + $sum('bpjs_jkk_company') + $sum('bpjs_jkm_company') + $sum('bpjs_jp_company');

        $run->update([
            'employee_count' => $items->count(), 'total_gross' => $sum('gross_earnings'), 'total_employer_bpjs' => $bpjsEr, 'total_employee_bpjs' => $bpjsEe,
            'total_pph21' => $sum('pph21'), 'total_other_deductions' => $sum('other_deductions') + $sum('loan_deduction'), 'total_net' => $sum('net_pay'),
            'total_disbursable' => (float) $items->where('is_held', false)->sum('net_pay'), 'total_employer_cost' => $sum('employer_cost'),
            'totals' => [
                'by_gl' => $byGl, 'by_department' => $byDept,
                'bpjs' => ['kes_employee' => $sum('bpjs_kes_employee'), 'kes_company' => $sum('bpjs_kes_company'), 'jht_employee' => $sum('bpjs_jht_employee'), 'jht_company' => $sum('bpjs_jht_company'),
                    'jkk_company' => $sum('bpjs_jkk_company'), 'jkm_company' => $sum('bpjs_jkm_company'), 'jp_employee' => $sum('bpjs_jp_employee'), 'jp_company' => $sum('bpjs_jp_company')],
                'loan' => $sum('loan_deduction'), 'other_deductions' => $sum('other_deductions'),
            ],
        ]);
    }

    /** @return \Illuminate\Support\Collection<int,Employee> */
    private function employeesFor(PayrollRun $run)
    {
        $q = Employee::query()->with('department')->where(fn ($w) => $w->whereNull('join_date')->orWhereDate('join_date', '<=', $run->period_end));

        if (in_array($run->type, ['bonus', 'correction'], true)) {
            $ids = PayrollInput::where('period_year', $run->period_year)->where('period_month', $run->period_month)->where('status', 'approved')->whereNull('applied_run_id')->where('type', '!=', 'overtime')->pluck('employee_id');

            return $q->whereIn('id', $ids)->get();
        }

        return $q->where(fn ($w) => $w->where('employment_status', 'active')
            ->orWhere(fn ($x) => $x->whereNotNull('resign_date')->whereDate('resign_date', '>=', $run->period_start)))->get();
    }

    /** @return array{ytd:array,same:array} */
    private function aggregates(PayrollRun $run): array
    {
        $base = PayrollItem::query()->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->where('payroll_runs.period_year', $run->period_year)->whereNotNull('payroll_runs.locked_at')
            ->whereNotIn('payroll_runs.status', ['cancelled', 'rejected'])->where('payroll_runs.id', '!=', $run->id);

        $ytd = (clone $base)->groupBy('payroll_items.employee_id')
            ->selectRaw('payroll_items.employee_id as eid, sum(payroll_items.tax_gross) as gross, sum(payroll_items.bpjs_jht_employee + payroll_items.bpjs_jp_employee) as pension, sum(payroll_items.pph21) as tax')->get();
        $same = (clone $base)->where('payroll_runs.period_month', $run->period_month)->groupBy('payroll_items.employee_id')
            ->selectRaw('payroll_items.employee_id as eid, sum(payroll_items.tax_gross) as gross, sum(payroll_items.pph21) as tax')->get();

        $out = ['ytd' => [], 'same' => []];
        foreach (OpeningBalance::where('year', $run->period_year)->get() as $o) {
            $out['ytd'][$o->employee_id] = ['gross' => $o->gross_ytd, 'pension' => $o->pension_ytd, 'tax' => $o->pph21_ytd];
        }
        foreach ($ytd as $r) {
            $cur = $out['ytd'][$r->eid] ?? ['gross' => 0, 'pension' => 0, 'tax' => 0];
            $out['ytd'][$r->eid] = ['gross' => $cur['gross'] + (float) $r->gross, 'pension' => $cur['pension'] + (float) $r->pension, 'tax' => $cur['tax'] + (float) $r->tax];
        }
        foreach ($same as $r) {
            $out['same'][$r->eid] = ['gross' => (float) $r->gross, 'tax' => (float) $r->tax];
        }

        return $out;
    }

    /** @return array<int,array{id:int,amount:float}> */
    private function loansDue(Employee $e, int $year, int $month): array
    {
        return EmployeeLoan::where('employee_id', $e->id)->where('status', 'active')->where('remaining', '>', 0)
            ->whereRaw('(start_year * 12 + start_month) <= ?', [$year * 12 + $month])->get()
            ->map(fn ($l) => ['id' => $l->id, 'amount' => min((float) $l->installment_amount, (float) $l->remaining)])->all();
    }
}
