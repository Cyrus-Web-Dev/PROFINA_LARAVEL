<?php

namespace App\Services\Calc;

use App\Support\RegulationSnapshot;

/**
 * PPh Pasal 21 pegawai: TER bulanan (Jan-Nov) + tarif Pasal 17 pada masa pajak terakhir (PP 58/2023, PMK 168/2023).
 * Murni fungsi (tanpa DB). Verifikasi hasilnya dengan kalkulator.pajak.go.id sebelum dipakai untuk produksi.
 */
class PphCalculator
{
    public function terCategory(RegulationSnapshot $reg, string $ptkp): string
    {
        $map = $reg->get('tax.ter_category');

        return $map[$ptkp] ?? 'A';
    }

    public function terRate(RegulationSnapshot $reg, string $category, float $gross): float
    {
        foreach ($reg->terTable($category) as [$upper, $rate]) {
            if ($upper === null || $gross <= $upper) {
                return (float) $rate;
            }
        }

        return 0.0;
    }

    /**
     * Pemotongan bulanan (Jan-Nov).
     *
     * @return array{tax:float,rate:float,category:string,surcharge:float}
     */
    public function monthly(RegulationSnapshot $reg, string $ptkp, float $gross, bool $hasNpwp): array
    {
        $cat = $this->terCategory($reg, $ptkp);
        $rate = $this->terRate($reg, $cat, $gross);
        $surcharge = $hasNpwp ? 1.0 : 1 + $reg->num('tax.npwp_surcharge_pct') / 100;

        return ['tax' => $reg->round($gross * $rate / 100 * $surcharge), 'rate' => $rate, 'category' => $cat, 'surcharge' => $surcharge];
    }

    /** Pajak progresif Pasal 17 atas PKP setahun (PKP dibulatkan ke bawah ribuan). */
    public function progressive(RegulationSnapshot $reg, float $pkp): float
    {
        $pkp = floor(max(0, $pkp) / 1000) * 1000;
        $tax = 0.0;
        $lower = 0.0;
        foreach ($reg->get('tax.pasal17') as [$upper, $rate]) {
            if ($pkp <= $lower) {
                break;
            }
            $top = $upper === null ? $pkp : min($pkp, (float) $upper);
            $tax += ($top - $lower) * $rate / 100;
            $lower = $upper === null ? $pkp : (float) $upper;
        }

        return $tax;
    }

    /**
     * Masa pajak terakhir (Desember atau bulan berhenti): hitung PPh setahun lalu kurangi yang sudah dipotong.
     *
     * @param  float  $annualGross  penghasilan bruto setahun s.d. masa ini (YTD termasuk bulan ini)
     * @param  float  $pensionYtd   iuran JHT+JP karyawan YTD
     * @param  int    $monthsWorked 1..12 (PTKP & biaya jabatan dihitung proporsional bila kurang dari 12)
     * @param  float  $taxPaidBefore PPh 21 yang sudah dipotong pada masa sebelumnya tahun ini
     * @return array{tax:float,detail:array<string,float|int>}
     */
    public function finalPeriod(RegulationSnapshot $reg, string $ptkp, bool $hasNpwp, float $annualGross, float $pensionYtd, int $monthsWorked, float $taxPaidBefore): array
    {
        $monthsWorked = max(1, min(12, $monthsWorked));
        $bj = $reg->get('tax.biaya_jabatan');
        $biayaJabatan = min($annualGross * $bj['pct'] / 100, $bj['cap_month'] * $monthsWorked);
        $neto = max(0, $annualGross - $biayaJabatan - $pensionYtd);
        $ptkpAnnual = (float) ($reg->get('tax.ptkp')[$ptkp] ?? $reg->get('tax.ptkp')['TK/0']) * $monthsWorked / 12;
        $pkp = max(0, $neto - $ptkpAnnual);
        $annualTax = $this->progressive($reg, $pkp);
        $surcharge = $hasNpwp ? 1.0 : 1 + $reg->num('tax.npwp_surcharge_pct') / 100;
        $annualTax = $reg->round($annualTax * $surcharge);

        return [
            'tax' => $reg->round($annualTax - $taxPaidBefore),
            'detail' => [
                'annual_gross' => $annualGross, 'biaya_jabatan' => $biayaJabatan, 'pension' => $pensionYtd,
                'neto' => $neto, 'ptkp' => $ptkpAnnual, 'pkp' => $pkp, 'annual_tax' => $annualTax,
                'tax_paid_before' => $taxPaidBefore, 'months_worked' => $monthsWorked,
            ],
        ];
    }
}
