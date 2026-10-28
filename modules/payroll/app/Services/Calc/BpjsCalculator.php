<?php

namespace App\Services\Calc;

use App\Support\RegulationSnapshot;

class BpjsCalculator
{
    /**
     * @param  float  $wage  dasar upah BPJS = gaji pokok + tunjangan tetap
     * @return array<string,float>
     */
    public function calculate(RegulationSnapshot $reg, float $wage, int $jkkClass = 1, bool $kes = true, bool $tk = true): array
    {
        $r = fn (float $v) => $reg->round($v);
        $out = array_fill_keys(['kes_employee', 'kes_company', 'jht_employee', 'jht_company', 'jkk_company', 'jkm_company', 'jp_employee', 'jp_company'], 0.0);

        if ($kes) {
            $base = min($wage, $reg->num('bpjs.kes.wage_cap'));
            $out['kes_employee'] = $r($base * $reg->num('bpjs.kes.rate_employee') / 100);
            $out['kes_company'] = $r($base * $reg->num('bpjs.kes.rate_company') / 100);
        }

        if ($tk) {
            $jkk = $reg->get('bpjs.jkk.rate');
            $jkkRate = (float) ($jkk[$jkkClass] ?? $jkk[(string) $jkkClass] ?? $jkk[1] ?? $jkk['1']);
            $jpBase = min($wage, $reg->num('bpjs.jp.wage_cap'));

            $out['jht_employee'] = $r($wage * $reg->num('bpjs.jht.rate_employee') / 100);
            $out['jht_company'] = $r($wage * $reg->num('bpjs.jht.rate_company') / 100);
            $out['jkk_company'] = $r($wage * $jkkRate / 100);
            $out['jkm_company'] = $r($wage * $reg->num('bpjs.jkm.rate') / 100);
            $out['jp_employee'] = $r($jpBase * $reg->num('bpjs.jp.rate_employee') / 100);
            $out['jp_company'] = $r($jpBase * $reg->num('bpjs.jp.rate_company') / 100);
        }

        return $out;
    }
}
