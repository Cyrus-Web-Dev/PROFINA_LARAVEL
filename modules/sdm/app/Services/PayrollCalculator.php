<?php

namespace App\Services;

use App\Models\Employee;

class PayrollCalculator
{
    /**
     * Hitung seluruh komponen payroll satu karyawan untuk satu periode.
     *
     * @param  array<int, array{label:string, amount:float}>  $allowances  Tunjangan tambahan
     * @param  array<int, array{label:string, amount:float}>  $otherDeductions  Potongan lain (kasbon, dsb)
     * @return array<string, mixed>
     */
    public function calculate(Employee $employee, array $allowances = [], array $otherDeductions = [], float $overtimeAmount = 0): array
    {
        $basicSalary = (float) $employee->base_salary;
        $totalAllowances = collect($allowances)->sum('amount');
        $totalOtherDeductions = collect($otherDeductions)->sum('amount');

        $bpjs = $this->calculateBpjs($basicSalary);
        $grossSalary = $basicSalary + $totalAllowances + $overtimeAmount;

        $pph21 = $this->calculatePph21Monthly(
            grossMonthly: $grossSalary,
            bpjsJhtEmployee: $bpjs['jht_employee'],
            bpjsJpEmployee: $bpjs['jp_employee'],
            ptkpStatus: $employee->ptkp_status ?? 'TK/0'
        );

        $totalDeductions = $bpjs['kesehatan_employee'] + $bpjs['jht_employee'] + $bpjs['jp_employee']
            + $pph21 + $totalOtherDeductions;

        $netSalary = $grossSalary - $totalDeductions;

        return [
            'basic_salary' => round($basicSalary, 2),
            'allowances' => $allowances,
            'other_deductions' => $otherDeductions,
            'overtime_amount' => round($overtimeAmount, 2),
            'bpjs_kesehatan_employee' => round($bpjs['kesehatan_employee'], 2),
            'bpjs_kesehatan_company' => round($bpjs['kesehatan_company'], 2),
            'bpjs_jht_employee' => round($bpjs['jht_employee'], 2),
            'bpjs_jht_company' => round($bpjs['jht_company'], 2),
            'bpjs_jkk_company' => round($bpjs['jkk_company'], 2),
            'bpjs_jkm_company' => round($bpjs['jkm_company'], 2),
            'bpjs_jp_employee' => round($bpjs['jp_employee'], 2),
            'bpjs_jp_company' => round($bpjs['jp_company'], 2),
            'pph21' => round($pph21, 2),
            'gross_salary' => round($grossSalary, 2),
            'total_deductions' => round($totalDeductions, 2),
            'net_salary' => round($netSalary, 2),
        ];
    }

    /** @return array<string, float> */
    public function calculateBpjs(float $basicSalary): array
    {
        $cfg = config('hr.bpjs');

        $kesehatanBase = min($basicSalary, $cfg['kesehatan']['salary_cap']);
        $jpBase = min($basicSalary, $cfg['jp']['salary_cap']);

        return [
            'kesehatan_employee' => $kesehatanBase * $cfg['kesehatan']['employee_pct'] / 100,
            'kesehatan_company' => $kesehatanBase * $cfg['kesehatan']['company_pct'] / 100,
            'jht_employee' => $basicSalary * $cfg['jht']['employee_pct'] / 100,
            'jht_company' => $basicSalary * $cfg['jht']['company_pct'] / 100,
            'jkk_company' => $basicSalary * $cfg['jkk']['company_pct'] / 100,
            'jkm_company' => $basicSalary * $cfg['jkm']['company_pct'] / 100,
            'jp_employee' => $jpBase * $cfg['jp']['employee_pct'] / 100,
            'jp_company' => $jpBase * $cfg['jp']['company_pct'] / 100,
        ];
    }

    /**
     * Estimasi PPh 21 bulanan memakai metode tarif progresif tahunan dibagi 12 (disetahunkan).
     * Ini pendekatan yang umum dipakai utk payroll internal; utk kepatuhan penuh (mis. metode TER
     * bulanan sesuai PP 58/2023), sebaiknya diverifikasi/disesuaikan dengan konsultan pajak.
     */
    public function calculatePph21Monthly(float $grossMonthly, float $bpjsJhtEmployee, float $bpjsJpEmployee, string $ptkpStatus): float
    {
        $cfg = config('hr.pph21');

        $grossAnnual = $grossMonthly * 12;
        $jabatanCost = min($grossAnnual * $cfg['jabatan_cost_pct'], $cfg['jabatan_cost_cap_monthly'] * 12);
        $bpjsDeductionAnnual = ($bpjsJhtEmployee + $bpjsJpEmployee) * 12;

        $netAnnual = max(0, $grossAnnual - $jabatanCost - $bpjsDeductionAnnual);
        $ptkp = $cfg['ptkp'][$ptkpStatus] ?? $cfg['ptkp']['TK/0'];
        $pkp = max(0, $netAnnual - $ptkp); // Penghasilan Kena Pajak, dibulatkan ke bawah ribuan terdekat
        $pkp = floor($pkp / 1000) * 1000;

        $taxAnnual = 0.0;
        $lowerBound = 0;
        foreach ($cfg['brackets'] as [$upperBound, $rate]) {
            if ($pkp <= $lowerBound) {
                break;
            }
            $taxableInBracket = min($pkp, $upperBound) - $lowerBound;
            $taxAnnual += $taxableInBracket * $rate;
            $lowerBound = $upperBound;
        }

        return $taxAnnual / 12;
    }
}
