<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\BudgetPlan;
use App\Models\Department;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penganggaran SDM: rencana anggaran per departemen x bulan x kategori, persetujuan, pengendalian saat payroll
 * (budget gate), realisasi vs anggaran, proyeksi akhir tahun, dan simulasi kenaikan. Anggaran yang aktif
 * didorong ke Keuangan (MKP) lewat webhook budget.approved.
 */
class BudgetService
{
    public const GL = ['basic' => 'salary_expense', 'allowance' => 'allowance_expense', 'overtime' => 'overtime_expense', 'bonus' => 'bonus_expense', 'thr' => 'thr_expense', 'bpjs_company' => 'bpjs_company_expense', 'other' => 'salary_expense'];

    /** Peta kategori baris komponen payroll -> kategori anggaran. */
    public const LINE_CAT = ['basic' => 'basic', 'fixed_allowance' => 'allowance', 'variable_allowance' => 'allowance', 'allowance' => 'allowance', 'reimbursement' => 'other', 'correction' => 'other', 'overtime' => 'overtime', 'bonus' => 'bonus', 'thr' => 'thr'];

    public function __construct(private ApprovalService $approvals, private WebhookService $webhooks)
    {
    }

    public function activePlan(int $year): ?BudgetPlan
    {
        return BudgetPlan::where('fiscal_year', $year)->where('status', 'active')->orderByDesc('version')->first();
    }

    /** @param array<int,array{department_id:int,month:int,category:string,amount:float,planned_headcount?:?int}> $lines */
    public function saveLines(BudgetPlan $plan, array $lines): void
    {
        if (! in_array($plan->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages(['plan' => 'Hanya anggaran berstatus draf/ditolak yang bisa diubah. Buat versi baru.']);
        }
        DB::transaction(function () use ($plan, $lines) {
            $plan->lines()->delete();
            foreach ($lines as $l) {
                if ((float) $l['amount'] <= 0 && empty($l['planned_headcount'])) {
                    continue;
                }
                $plan->lines()->create(['department_id' => $l['department_id'], 'month' => $l['month'], 'category' => $l['category'], 'amount' => $l['amount'], 'planned_headcount' => $l['planned_headcount'] ?? null]);
            }
            $plan->update(['total_amount' => $plan->lines()->sum('amount'), 'status' => 'draft']);
        });
    }

    public function newVersion(int $year, ?User $by, ?BudgetPlan $from = null): BudgetPlan
    {
        $version = (int) BudgetPlan::where('fiscal_year', $year)->max('version') + 1;
        $plan = BudgetPlan::create(['fiscal_year' => $year, 'version' => $version, 'name' => "Anggaran SDM {$year} v{$version}", 'status' => 'draft', 'created_by' => $by?->id]);
        if ($from) {
            foreach ($from->lines as $l) {
                $plan->lines()->create($l->only(['department_id', 'month', 'category', 'amount', 'planned_headcount']));
            }
            $plan->update(['total_amount' => $plan->lines()->sum('amount')]);
        }
        AuditLedger::record('budget.created', $plan, ['year' => $year, 'version' => $version], $plan->name, $by?->id);

        return $plan;
    }

    public function submit(BudgetPlan $plan, User $user): void
    {
        if (! in_array($plan->status, ['draft', 'rejected'], true) || $plan->lines()->count() === 0) {
            throw ValidationException::withMessages(['plan' => 'Anggaran harus berstatus draf dan memiliki baris sebelum diajukan.']);
        }
        $plan->update(['status' => 'submitted', 'submitted_by' => $user->id, 'submitted_at' => now(), 'rejection_note' => null]);
        $this->approvals->start($plan, [['budgets.approve', 'Persetujuan anggaran (Keuangan/Direksi)']]);
        AuditLedger::record('budget.submitted', $plan, ['total' => $plan->total_amount], $plan->name, $user->id);
    }

    public function decide(BudgetPlan $plan, User $user, string $decision, ?string $note): BudgetPlan
    {
        if ($plan->status !== 'submitted') {
            throw ValidationException::withMessages(['plan' => 'Anggaran tidak sedang menunggu keputusan.']);
        }

        return DB::transaction(function () use ($plan, $user, $decision, $note) {
            $res = $this->approvals->act($plan, $user, $decision, $note, $plan->submitted_by);
            if ($decision === 'rejected') {
                $plan->update(['status' => 'rejected', 'rejection_note' => $note]);

                return $plan;
            }
            if ($res['complete']) {
                BudgetPlan::where('fiscal_year', $plan->fiscal_year)->where('status', 'active')->update(['status' => 'superseded']);
                $plan->update(['status' => 'active', 'approved_by' => $user->id, 'approved_at' => now(), 'activated_at' => now()]);
                $this->pushToFinance($plan->fresh());
            }

            return $plan->fresh();
        });
    }

    /** Ringkasan anggaran aktif per kunci buku besar per bulan (tanpa rincian departemen) untuk Keuangan. */
    public function financePayload(BudgetPlan $plan): array
    {
        $rows = [];
        foreach ($plan->lines as $l) {
            $gl = self::GL[$l->category] ?? 'salary_expense';
            $rows[$gl][$l->month] = ($rows[$gl][$l->month] ?? 0) + (float) $l->amount;
        }
        $out = [];
        foreach ($rows as $gl => $months) {
            foreach ($months as $m => $amt) {
                $out[] = ['gl_key' => $gl, 'month' => $m, 'amount' => round($amt, 2)];
            }
        }

        return ['fiscal_year' => $plan->fiscal_year, 'version' => $plan->version, 'name' => $plan->name, 'total' => $plan->total_amount, 'lines' => $out];
    }

    public function pushToFinance(BudgetPlan $plan): void
    {
        $plan->loadMissing('lines');
        $this->webhooks->dispatch('budget.approved', $this->financePayload($plan));
        $plan->update(['pushed_to_finance_at' => now()]);
        AuditLedger::record('budget.activated', $plan, ['year' => $plan->fiscal_year, 'version' => $plan->version, 'total' => $plan->total_amount], $plan->name);
    }

    /**
     * Realisasi dari putaran TERKUNCI (+ opsional putaran yang sedang dihitung).
     *
     * @return array<int, array<int, array<string,float>>> [dept_id][month][category] => nominal
     */
    public function actuals(int $year, ?PayrollRun $including = null): array
    {
        $out = [];
        $q = PayrollItem::query()->with('run:id,period_month,period_year,locked_at,status')
            ->whereHas('run', fn ($r) => $r->where('period_year', $year)->whereNotIn('status', ['cancelled', 'rejected'])
                ->where(fn ($w) => $w->whereNotNull('locked_at')->when($including, fn ($x) => $x->orWhere('id', $including->id))));

        $q->chunkById(500, function ($items) use (&$out) {
            foreach ($items as $it) {
                $d = (int) $it->department_id;
                $m = (int) $it->run->period_month;
                foreach ($it->lines as $l) {
                    if ($l['type'] !== 'earning') {
                        continue;
                    }
                    $cat = self::LINE_CAT[$l['cat']] ?? 'other';
                    $out[$d][$m][$cat] = ($out[$d][$m][$cat] ?? 0) + (float) $l['amount'];
                }
                $bp = (float) $it->bpjs_kes_company + $it->bpjs_jht_company + $it->bpjs_jkk_company + $it->bpjs_jkm_company + $it->bpjs_jp_company;
                $out[$d][$m]['bpjs_company'] = ($out[$d][$m]['bpjs_company'] ?? 0) + $bp;
            }
        });

        return $out;
    }

    /** Pengendalian anggaran untuk satu putaran: biaya bulan ini per departemen vs anggaran bulan ini. */
    public function checkRun(PayrollRun $run): array
    {
        $plan = $this->activePlan((int) $run->period_year);
        if (! $plan) {
            return ['has_plan' => false, 'plan' => null, 'rows' => []];
        }
        $actual = $this->actuals((int) $run->period_year, $run);
        $budget = BudgetLine::where('budget_plan_id', $plan->id)->where('month', $run->period_month)->get()->groupBy('department_id');
        $names = Department::pluck('name', 'id');
        $rows = [];
        foreach (array_unique(array_merge($budget->keys()->all(), array_keys($actual))) as $d) {
            $b = (float) ($budget[$d] ?? collect())->sum('amount');
            $a = array_sum($actual[$d][$run->period_month] ?? []);
            $rows[] = ['department_id' => (int) $d, 'name' => $names[$d] ?? '(tanpa departemen)', 'budget' => $b, 'actual' => $a, 'over' => max(0, $a - $b), 'pct' => $b > 0 ? round($a / $b * 100, 1) : null];
        }

        return ['has_plan' => true, 'plan' => ['id' => $plan->id, 'version' => $plan->version], 'rows' => $rows];
    }

    /**
     * Anggaran vs realisasi + proyeksi akhir tahun (run-rate rata-rata 3 bulan terakhir).
     *
     * @return array{rows:array,totals:array,forecast:array}
     */
    public function report(int $year): array
    {
        $plan = $this->activePlan($year);
        $actual = $this->actuals($year);
        $budget = $plan ? BudgetLine::where('budget_plan_id', $plan->id)->get() : collect();
        $names = Department::pluck('name', 'id');
        $depts = array_unique(array_merge($budget->pluck('department_id')->all(), array_keys($actual)));

        $rows = [];
        $monthBudget = array_fill(1, 12, 0.0);
        $monthActual = array_fill(1, 12, 0.0);
        foreach ($depts as $d) {
            $r = ['department_id' => (int) $d, 'name' => $names[$d] ?? '(tanpa departemen)', 'months' => []];
            for ($m = 1; $m <= 12; $m++) {
                $b = (float) $budget->where('department_id', $d)->where('month', $m)->sum('amount');
                $a = array_sum($actual[$d][$m] ?? []);
                $r['months'][$m] = ['budget' => $b, 'actual' => $a];
                $monthBudget[$m] += $b;
                $monthActual[$m] += $a;
            }
            $r['budget'] = array_sum(array_column($r['months'], 'budget'));
            $r['actual'] = array_sum(array_column($r['months'], 'actual'));
            $rows[] = $r;
        }

        $lastMonth = 0;
        for ($m = 12; $m >= 1; $m--) {
            if ($monthActual[$m] > 0) {
                $lastMonth = $m;
                break;
            }
        }
        $recent = array_slice(array_filter(array_slice($monthActual, 0, $lastMonth, true), fn ($v) => $v > 0), -3);
        $runRate = $recent ? array_sum($recent) / count($recent) : 0;
        $projected = array_sum($monthActual) + $runRate * (12 - $lastMonth);
        $annualBudget = array_sum($monthBudget);

        return [
            'plan' => $plan ? ['id' => $plan->id, 'version' => $plan->version, 'name' => $plan->name] : null,
            'rows' => $rows,
            'totals' => ['budget_by_month' => array_values($monthBudget), 'actual_by_month' => array_values($monthActual), 'budget' => $annualBudget, 'actual' => array_sum($monthActual)],
            'forecast' => ['last_actual_month' => $lastMonth, 'run_rate' => $runRate, 'projected_year_end' => $projected, 'variance' => $projected - $annualBudget],
        ];
    }

    /**
     * Simulasi: kenaikan gaji X% pada departemen terpilih -> dampak biaya setahun (gaji pokok+tunjangan tetap + BPJS perusahaan).
     *
     * @return array{rows:array,total_monthly:float,total_annual:float}
     */
    public function simulateRaise(float $pct, ?array $departmentIds, int $fromMonth): array
    {
        $rows = [];
        $months = max(0, 13 - $fromMonth);
        $items = \App\Models\Employee::query()->where('employment_status', 'active')->with(['activeContract.components.component', 'department'])
            ->when($departmentIds, fn ($q) => $q->whereIn('department_id', $departmentIds))->get();

        $agg = [];
        foreach ($items as $e) {
            $c = $e->activeContract;
            if (! $c) {
                continue;
            }
            $fixed = $c->components->filter(fn ($x) => $x->component->type === 'earning' && $x->component->category === 'fixed_allowance')->sum('amount');
            $wage = $c->base_salary + $fixed;
            $uplift = $c->base_salary * $pct / 100; // kenaikan atas gaji pokok
            $bpjs = $uplift * (0.04 + 0.037 + 0.003 + 0.02) + $uplift * 0.0024; // estimasi: Kes 4% + JHT 3,7% + JKM 0,3% + JP 2% + JKK ~0,24%
            $d = $e->department?->name ?? '(tanpa departemen)';
            $agg[$d]['employees'] = ($agg[$d]['employees'] ?? 0) + 1;
            $agg[$d]['monthly'] = ($agg[$d]['monthly'] ?? 0) + $uplift + $bpjs;
            $agg[$d]['current'] = ($agg[$d]['current'] ?? 0) + $wage;
        }
        $total = 0.0;
        foreach ($agg as $name => $v) {
            $rows[] = ['department' => $name, 'employees' => $v['employees'], 'current_monthly_wage' => $v['current'], 'monthly_impact' => $v['monthly'], 'impact_until_year_end' => $v['monthly'] * $months];
            $total += $v['monthly'];
        }

        return ['rows' => $rows, 'total_monthly' => $total, 'total_annual' => $total * $months];
    }
}
