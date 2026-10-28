<?php

namespace App\Services\Calc;

use App\Models\CompensationContract;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Support\RegulationSnapshot;
use Carbon\Carbon;

/**
 * Orkestrator perhitungan SATU karyawan untuk SATU putaran. Murni (input -> output array), tidak menulis database,
 * sehingga hasilnya bisa dihitung ulang/diverifikasi independen oleh PayrollIntegrity.
 *
 * $ctx: attendance(?AttendanceSnapshot), inputs(iterable PayrollInput), loans(array{id,amount}[]),
 *       ytd{gross,pension,tax}, same_month{gross,tax}, final_period(bool)
 */
class PayrollCalculator
{
    public function __construct(private BpjsCalculator $bpjs, private PphCalculator $pph, private OvertimeCalculator $ot)
    {
    }

    public function regular(PayrollRun $run, Employee $e, CompensationContract $c, RegulationSnapshot $reg, array $ctx): array
    {
        $r = fn (float $v) => $reg->round($v);
        $factor = $this->prorationFactor($run, $e, $c);
        $lines = [];
        $add = function (string $code, string $name, string $type, string $cat, float $amount, bool $taxable, string $gl, bool $bpjsBase = false) use (&$lines) {
            if (abs($amount) < 0.005) {
                return;
            }
            $lines[] = compact('code', 'name', 'type', 'cat', 'amount', 'taxable', 'gl') + ['bpjs_base' => $bpjsBase];
        };

        $basic = $r($c->base_salary * $factor);
        $add('GAPOK', 'Gaji pokok', 'earning', 'basic', $basic, true, 'salary_expense', true);

        $fixedFull = 0.0; // upah tetap penuh (tanpa prorata) - dasar lembur
        $fixedPaid = 0.0;
        foreach ($c->components as $cc) {
            $comp = $cc->component;
            $amt = $r($cc->amount * ($comp->prorated ? $factor : 1));
            $add($comp->code, $comp->name, $comp->type, $comp->category, $amt, (bool) $comp->taxable, $comp->gl_key, (bool) $comp->bpjs_base);
            if ($comp->type === 'earning' && $comp->category === 'fixed_allowance') {
                $fixedPaid += $amt;
                if ($comp->bpjs_base) {
                    $fixedFull += $cc->amount;
                }
            }
        }

        // Potongan ketidakhadiran tanpa upah (alpha + cuti tidak berbayar) - angka dari absensi SDM.
        $att = $ctx['attendance'] ?? null;
        $absent = $att ? (float) $att->alpha_days + (float) $att->unpaid_leave_days : 0.0;
        $daysScheduled = $att ? (int) $att->scheduled_days : 0;
        if ($absent > 0) {
            $div = config('pay.payroll.absence_divisor', 'scheduled');
            $divisor = $div === 'scheduled' ? max(1, $daysScheduled) : max(1, (int) $div);
            $ded = min($r(($basic + $fixedPaid) / $divisor * $absent), $basic + $fixedPaid);
            $add('POT_ABSEN', 'Potongan ketidakhadiran ('.rtrim(rtrim(number_format($absent, 1), '0'), '.').' hari)', 'earning', 'basic', -$ded, true, 'salary_expense');
        }

        // Input variabel yang sudah DISETUJUI.
        $otHours = 0.0;
        $otWage = $c->base_salary + $fixedFull;
        $glMap = ['bonus' => 'bonus_expense', 'allowance' => 'allowance_expense', 'reimbursement' => 'reimbursement_expense', 'correction' => 'salary_expense', 'deduction' => 'other_deduction_payable'];
        foreach ($ctx['inputs'] ?? [] as $in) {
            if ($in->type === 'overtime') {
                if (! $c->overtime_eligible) {
                    continue;
                }
                $amt = $this->ot->amount($reg, $otWage, $in->day_type ?: 'weekday', (float) $in->hours);
                $otHours += (float) $in->hours;
                $add('LEMBUR', 'Lembur '.$in->work_date?->format('d/m').' ('.rtrim(rtrim(number_format((float) $in->hours, 2), '0'), '.').' jam)', 'earning', 'overtime', $amt, true, 'overtime_expense');
                continue;
            }
            $comp = $in->component;
            $isDed = $in->type === 'deduction';
            $add($comp->code ?? 'INP-'.strtoupper($in->type), $in->description, $isDed ? 'deduction' : 'earning', $in->type, $r((float) $in->amount),
                $comp ? (bool) $comp->taxable : ! in_array($in->type, ['reimbursement', 'deduction'], true), $comp->gl_key ?? $glMap[$in->type]);
        }

        $bpjsWage = collect($lines)->where('type', 'earning')->where('bpjs_base', true)->sum('amount');
        $bp = $this->bpjs->calculate($reg, max(0, $bpjsWage), (int) $c->jkk_class, (bool) $c->bpjs_kes_enrolled, (bool) $c->bpjs_tk_enrolled);

        $out = $this->build($run, $e, $c, $reg, $lines, $bp, $ctx);
        $out['days_scheduled'] = $daysScheduled;
        $out['days_present'] = $att ? (float) $att->present_days : 0;
        $out['days_absent'] = $att ? (float) $att->alpha_days : 0;
        $out['days_unpaid_leave'] = $att ? (float) $att->unpaid_leave_days : 0;
        $out['proration_factor'] = $factor;
        $out['overtime_hours'] = $otHours;

        return $out;
    }

    /** THR: (masa kerja bulan, maks 12)/12 x (gaji pokok + tunjangan tetap) pada tanggal hari raya (Permenaker 6/2016). */
    public function thr(PayrollRun $run, Employee $e, CompensationContract $c, RegulationSnapshot $reg, array $ctx): ?array
    {
        $p = $reg->get('labor.thr');
        if (! $c->thr_eligible || ! $e->join_date || ! $run->thr_holiday_date) {
            return null;
        }
        $months = (int) floor(abs($e->join_date->diffInMonths($run->thr_holiday_date)));
        if ($months < $p['min_months']) {
            return null;
        }
        $fixed = $c->components->filter(fn ($x) => $x->component->type === 'earning' && $x->component->category === 'fixed_allowance' && $x->component->bpjs_base)->sum('amount');
        $amount = $reg->round(min($months, $p['full_months']) / $p['full_months'] * ($c->base_salary + $fixed));
        $lines = [['code' => 'THR', 'name' => 'Tunjangan Hari Raya ('.min($months, $p['full_months']).'/'.$p['full_months'].' bulan)', 'type' => 'earning', 'cat' => 'thr', 'amount' => $amount, 'taxable' => true, 'gl' => 'thr_expense', 'bpjs_base' => false]];
        $out = $this->build($run, $e, $c, $reg, $lines, $this->bpjs->calculate($reg, 0, 1, false, false), $ctx);
        $out['proration_factor'] = 1;

        return $out;
    }

    /** Bonus/koreksi/tunjangan insidentil yang sudah disetujui, dibayar di luar siklus gaji bulanan. */
    public function extra(PayrollRun $run, Employee $e, CompensationContract $c, RegulationSnapshot $reg, array $ctx): ?array
    {
        $lines = [];
        $glMap = ['bonus' => 'bonus_expense', 'allowance' => 'allowance_expense', 'reimbursement' => 'reimbursement_expense', 'correction' => 'salary_expense', 'deduction' => 'other_deduction_payable'];
        foreach ($ctx['inputs'] ?? [] as $in) {
            if ($in->type === 'overtime') {
                continue;
            }
            $isDed = $in->type === 'deduction';
            $lines[] = ['code' => 'INP-'.strtoupper($in->type), 'name' => $in->description, 'type' => $isDed ? 'deduction' : 'earning', 'cat' => $in->type, 'amount' => $reg->round((float) $in->amount),
                'taxable' => ! in_array($in->type, ['reimbursement', 'deduction'], true), 'gl' => $glMap[$in->type], 'bpjs_base' => false];
        }
        if (! $lines) {
            return null;
        }
        $out = $this->build($run, $e, $c, $reg, $lines, $this->bpjs->calculate($reg, 0, 1, false, false), $ctx);
        $out['proration_factor'] = 1;

        return $out;
    }

    /** Hitung pajak, potongan, neto & biaya perusahaan dari baris komponen + BPJS. */
    private function build(PayrollRun $run, Employee $e, CompensationContract $c, RegulationSnapshot $reg, array $lines, array $bp, array $ctx): array
    {
        $r = fn (float $v) => $reg->round($v);
        $lines = array_values($lines);

        $earn = fn (callable $f) => array_sum(array_map(fn ($l) => $l['type'] === 'earning' && $f($l) ? $l['amount'] : 0, $lines));
        $gross = $earn(fn () => true);
        $basic = $earn(fn ($l) => $l['cat'] === 'basic');
        $fixed = $earn(fn ($l) => $l['cat'] === 'fixed_allowance');
        $variable = $gross - $basic - $fixed;
        $lineDeductions = array_sum(array_map(fn ($l) => $l['type'] === 'deduction' ? $l['amount'] : 0, $lines));

        // --- PPh 21 ---
        $premiums = 0.0;
        foreach ((array) config('pay.payroll.taxable_employer_premiums', ['kes', 'jkk', 'jkm']) as $k) {
            $premiums += match ($k) { 'kes' => $bp['kes_company'], 'jkk' => $bp['jkk_company'], 'jkm' => $bp['jkm_company'], default => 0 };
        }
        $taxGross = $earn(fn ($l) => $l['taxable']) + $premiums;
        $hasNpwp = (bool) $e->has_npwp;
        $pension = $bp['jht_employee'] + $bp['jp_employee'];
        $detail = ['premiums' => $premiums];
        $category = null;
        $rate = null;

        if ($c->tax_scheme === 'exempt' || $taxGross <= 0 && empty($ctx['final_period'])) {
            $pph = 0.0;
            $method = $c->tax_scheme === 'exempt' ? 'exempt' : 'none';
        } elseif (! empty($ctx['final_period'])) {
            $ytd = $ctx['ytd'] ?? ['gross' => 0, 'pension' => 0, 'tax' => 0];
            $months = $this->monthsWorked($e, $run);
            $res = $this->pph->finalPeriod($reg, $e->ptkp_status, $hasNpwp, $ytd['gross'] + $taxGross, $ytd['pension'] + $pension, $months, $ytd['tax']);
            $pph = $res['tax'];
            $method = 'final';
            $detail += $res['detail'];
        } else {
            $sm = $ctx['same_month'] ?? ['gross' => 0, 'tax' => 0];
            $m = $this->pph->monthly($reg, $e->ptkp_status, $sm['gross'] + $taxGross, $hasNpwp);
            $pph = max(0, $m['tax'] - $sm['tax']);
            $method = 'ter';
            $category = $m['category'];
            $rate = $m['rate'];
            $detail += ['month_gross_total' => $sm['gross'] + $taxGross, 'same_month_tax_before' => $sm['tax'], 'surcharge' => $m['surcharge']];
        }

        // --- Potongan: kasbon dibatasi % dari penghasilan setelah BPJS & pajak ---
        $bpjsEmployee = $bp['kes_employee'] + $bp['jht_employee'] + $bp['jp_employee'];
        $capBase = max(0, $gross - $bpjsEmployee - max(0, $pph) - $lineDeductions);
        $room = $capBase * (float) config('pay.controls.deduction_cap_pct', 50) / 100;
        $loanApplied = [];
        $loanTotal = 0.0;
        foreach ($ctx['loans'] ?? [] as $loan) {
            $amt = min($loan['amount'], max(0, $room - $loanTotal));
            if ($amt > 0) {
                $loanApplied[] = ['id' => $loan['id'], 'amount' => $r($amt)];
                $loanTotal += $r($amt);
            }
        }
        if ($loanTotal > 0) {
            $lines[] = ['code' => 'KASBON', 'name' => 'Cicilan kasbon', 'type' => 'deduction', 'cat' => 'loan', 'amount' => $loanTotal, 'taxable' => false, 'gl' => 'employee_receivable', 'bpjs_base' => false];
        }
        $other = $lineDeductions;
        $totalDed = $bpjsEmployee + $pph + $loanTotal + $other;
        $net = $gross - $totalDed;
        $employerBpjs = $bp['kes_company'] + $bp['jht_company'] + $bp['jkk_company'] + $bp['jkm_company'] + $bp['jp_company'];

        return [
            'lines' => $lines, 'basic_salary' => $basic, 'fixed_allowances' => $fixed, 'variable_earnings' => $variable, 'gross_earnings' => $gross,
            'bpjs_kes_employee' => $bp['kes_employee'], 'bpjs_kes_company' => $bp['kes_company'], 'bpjs_jht_employee' => $bp['jht_employee'],
            'bpjs_jht_company' => $bp['jht_company'], 'bpjs_jkk_company' => $bp['jkk_company'], 'bpjs_jkm_company' => $bp['jkm_company'],
            'bpjs_jp_employee' => $bp['jp_employee'], 'bpjs_jp_company' => $bp['jp_company'],
            'tax_gross' => $taxGross, 'pph21' => $pph, 'pph21_method' => $method, 'ter_category' => $category, 'ter_rate' => $rate, 'tax_detail' => $detail,
            'loan_deduction' => $loanTotal, 'other_deductions' => $other, 'total_employee_deductions' => $totalDed,
            'net_pay' => $net, 'employer_cost' => $gross + $employerBpjs, 'loan_applied' => $loanApplied,
        ];
    }

    /** Faktor prorata kalender (masuk/keluar/kontrak mulai di tengah bulan). */
    public function prorationFactor(PayrollRun $run, Employee $e, CompensationContract $c): float
    {
        $ps = Carbon::parse($run->period_start)->startOfDay();
        $pe = Carbon::parse($run->period_end)->startOfDay();
        $from = $ps->copy();
        foreach ([$e->join_date, $c->effective_date] as $d) {
            if ($d && Carbon::parse($d)->gt($from)) {
                $from = Carbon::parse($d)->startOfDay();
            }
        }
        $to = $pe->copy();
        $supersededEnd = $c->superseded_on ? Carbon::parse($c->superseded_on)->subDay() : null;
        foreach ([$e->resign_date, $c->end_date, $supersededEnd] as $d) {
            if ($d && Carbon::parse($d)->lt($to)) {
                $to = Carbon::parse($d)->startOfDay();
            }
        }
        if ($to->lt($from)) {
            return 0.0;
        }

        return min(1.0, round(((int) floor(abs($from->diffInDays($to))) + 1) / $ps->daysInMonth, 6));
    }

    /** Jumlah bulan bekerja pada tahun berjalan s.d. bulan putaran (untuk PTKP & biaya jabatan proporsional). */
    public function monthsWorked(Employee $e, PayrollRun $run): int
    {
        $start = 1;
        if ($e->join_date && (int) $e->join_date->year === (int) $run->period_year) {
            $start = (int) $e->join_date->month;
        }

        return max(1, (int) $run->period_month - $start + 1);
    }
}
