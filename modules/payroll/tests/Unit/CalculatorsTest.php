<?php

namespace Tests\Unit;

use App\Models\CompensationContract;
use App\Models\ContractComponent;
use App\Models\Employee;
use App\Models\PayComponent;
use App\Models\PayrollRun;
use App\Services\AuditLedger;
use App\Services\Calc\BpjsCalculator;
use App\Services\Calc\OvertimeCalculator;
use App\Services\Calc\PayrollCalculator;
use App\Services\Calc\PphCalculator;
use App\Support\RegulationSnapshot;
use Database\Seeders\RegulationData;
use Tests\TestCase;

/** Angka pada tes ini dihitung manual (lihat komentar) dan TIDAK memakai database. */
class CalculatorsTest extends TestCase
{
    private function reg(string $asOf = '2026-10-01'): RegulationSnapshot
    {
        $rows = array_map(fn ($p) => [$p[0], $p[1], $p[2]], RegulationData::params());
        $ter = [];
        foreach (RegulationData::terTables() as $cat => $tiers) {
            foreach ($tiers as [$upper, $rate]) {
                $ter[$cat][] = [$upper === null ? null : (float) $upper, (float) $rate];
            }
        }

        return RegulationSnapshot::build($rows, $ter, $asOf, true);
    }

    public function test_ter_tables_are_complete_and_monotonic(): void
    {
        $t = RegulationData::terTables();
        $this->assertCount(44, $t['A']);
        $this->assertCount(40, $t['B']);
        $this->assertCount(41, $t['C']);
        foreach ($t as $tiers) {
            $this->assertNull(end($tiers)[0]);
            $this->assertSame(34.0, (float) end($tiers)[1]);
        }
    }

    public function test_ter_monthly_tax(): void
    {
        $p = new PphCalculator();
        $reg = $this->reg();
        $this->assertSame(200000.0, $p->monthly($reg, 'K/0', 10000000, true)['tax']);      // A, 2%
        $this->assertSame(240000.0, $p->monthly($reg, 'K/0', 10000000, false)['tax']);     // tanpa NPWP: x1,2
        $this->assertSame(0.0, $p->monthly($reg, 'TK/0', 5400000, true)['tax']);           // batas bebas pajak A
        $this->assertSame(360000.0, $p->monthly($reg, 'K/1', 12000000, true)['tax']);      // B: 12,0jt => 3%
        $this->assertSame('C', $p->monthly($reg, 'K/3', 7000000, true)['category']);
    }

    public function test_progressive_tax_article_17(): void
    {
        $p = new PphCalculator();
        $reg = $this->reg();
        $this->assertSame(3000000.0, $p->progressive($reg, 60000000));
        $this->assertSame(9000000.0, $p->progressive($reg, 100000000));                    // 3jt + 40jt x 15%
        $this->assertSame(3000000.0, $p->progressive($reg, 60000999));                     // dibulatkan ke bawah ribuan -> 60.000.000
    }

    public function test_final_period_true_up(): void
    {
        $p = new PphCalculator();
        // TK/0, bruto setahun 120jt, iuran pensiun 2,4jt: biaya jabatan 6jt -> neto 111,6jt -> PKP 57,6jt -> pajak 2,88jt; sudah dipotong 2jt -> kurang 880rb
        $r = $p->finalPeriod($this->reg(), 'TK/0', true, 120000000, 2400000, 12, 2000000);
        $this->assertSame(880000.0, $r['tax']);
        $this->assertSame(57600000.0, (float) $r['detail']['pkp']);
        // lebih potong -> negatif (dikembalikan)
        $this->assertSame(-120000.0, $p->finalPeriod($this->reg(), 'TK/0', true, 120000000, 2400000, 12, 3000000)['tax']);
    }

    public function test_bpjs(): void
    {
        $b = (new BpjsCalculator())->calculate($this->reg(), 8000000, 1);
        $this->assertSame([80000.0, 320000.0, 160000.0, 296000.0, 19200.0, 24000.0, 80000.0, 160000.0], array_values($b));

        $hi = (new BpjsCalculator())->calculate($this->reg(), 20000000, 1);
        $this->assertSame(120000.0, $hi['kes_employee']);     // dibatasi Rp12 juta
        $this->assertSame(110863.0, $hi['jp_employee']);       // batas JP Maret 2026: 11.086.300
        $this->assertSame(400000.0, $hi['jht_employee']);      // JHT tanpa batas

        $old = (new BpjsCalculator())->calculate($this->reg('2025-06-01'), 20000000, 1);
        $this->assertSame(105474.0, $old['jp_employee']);      // batas JP periode lama 10.547.400
    }

    public function test_overtime(): void
    {
        $o = new OvertimeCalculator();
        $reg = $this->reg();
        $this->assertSame(550000.0, $o->amount($reg, 17300000, 'weekday', 3));    // 100rb/jam x (1,5 + 2 + 2)
        $this->assertSame(1900000.0, $o->amount($reg, 17300000, 'restday', 9));   // 8x2 + 1x3 = 19
        $this->assertSame(2700000.0, $o->amount($reg, 17300000, 'holiday', 11));  // 16 + 3 + 2x4 = 27
    }

    public function test_full_regular_item_gross_to_net(): void
    {
        $reg = $this->reg();
        $calc = new PayrollCalculator(new BpjsCalculator(), new PphCalculator(), new OvertimeCalculator());

        $run = new PayrollRun(['period_year' => 2026, 'period_month' => 10, 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'type' => 'regular']);
        $emp = new Employee(['ptkp_status' => 'TK/0', 'has_npwp' => true]);
        $comp = new PayComponent(['code' => 'T_JABATAN', 'name' => 'Tunjangan jabatan', 'type' => 'earning', 'category' => 'fixed_allowance', 'taxable' => true, 'bpjs_base' => true, 'prorated' => true, 'gl_key' => 'allowance_expense']);
        $cc = new ContractComponent(['amount' => 2000000]);
        $cc->setRelation('component', $comp);
        $c = new CompensationContract(['base_salary' => 10000000, 'effective_date' => '2026-01-01', 'jkk_class' => 1, 'bpjs_kes_enrolled' => true, 'bpjs_tk_enrolled' => true, 'tax_scheme' => 'ter', 'overtime_eligible' => false]);
        $c->setRelation('components', collect([$cc]));

        $r = $calc->regular($run, $emp, $c, $reg, ['attendance' => null, 'inputs' => [], 'loans' => [], 'ytd' => ['gross' => 0, 'pension' => 0, 'tax' => 0], 'same_month' => ['gross' => 0, 'tax' => 0], 'final_period' => false]);

        $this->assertSame(12000000.0, $r['gross_earnings']);
        // bruto pajak = 12.000.000 + Kes pers. 480.000 + JKK 28.800 + JKM 36.000 = 12.544.800 -> TER A 5% = 627.240
        $this->assertSame(12544800.0, $r['tax_gross']);
        $this->assertSame(627240.0, $r['pph21']);
        // bersih = 12.000.000 - (Kes 120.000 + JHT 240.000 + JP 110.863) - 627.240
        $this->assertSame(10901897.0, $r['net_pay']);
        // biaya perusahaan = 12.000.000 + (480.000 + 444.000 + 28.800 + 36.000 + 221.726)
        $this->assertSame(13210526.0, $r['employer_cost']);
    }

    public function test_audit_hash_is_deterministic_and_sensitive(): void
    {
        $a = AuditLedger::hashOf(AuditLedger::GENESIS, 1, 'run.locked', 5, 'PayrollRun', 9, '{"net":1000}', '2026-10-01T00:00:00.000000Z');
        $b = AuditLedger::hashOf(AuditLedger::GENESIS, 1, 'run.locked', 5, 'PayrollRun', 9, '{"net":1000}', '2026-10-01T00:00:00.000000Z');
        $c = AuditLedger::hashOf(AuditLedger::GENESIS, 1, 'run.locked', 5, 'PayrollRun', 9, '{"net":1001}', '2026-10-01T00:00:00.000000Z');
        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
        $this->assertSame(64, strlen($a));
    }

    public function test_contract_terms_hash_detects_change(): void
    {
        $t = ['employee_id' => 1, 'base_salary' => 10000000, 'effective_date' => '2026-01-01', 'end_date' => null, 'grade_id' => null, 'contract_type' => 'pkwt', 'jkk_class' => 1,
            'bpjs_kes_enrolled' => true, 'bpjs_tk_enrolled' => true, 'tax_scheme' => 'ter', 'thr_eligible' => true, 'overtime_eligible' => false];
        $h1 = CompensationContract::hashTerms($t, [['pay_component_id' => 1, 'amount' => 500000]]);
        $this->assertSame($h1, CompensationContract::hashTerms($t, [['pay_component_id' => 1, 'amount' => '500000.00']]));
        $this->assertNotSame($h1, CompensationContract::hashTerms(['base_salary' => 10000001] + $t, [['pay_component_id' => 1, 'amount' => 500000]]));
    }
}
