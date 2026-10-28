<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\PayComponent;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // code, name, type, category, taxable, bpjs_base, prorated, gl_key
            ['T_JABATAN', 'Tunjangan jabatan', 'earning', 'fixed_allowance', true, true, true, 'allowance_expense'],
            ['T_TETAP', 'Tunjangan tetap', 'earning', 'fixed_allowance', true, true, true, 'allowance_expense'],
            ['T_TRANSPORT', 'Tunjangan transport', 'earning', 'fixed_allowance', true, false, true, 'allowance_expense'],
            ['T_MAKAN', 'Tunjangan makan', 'earning', 'fixed_allowance', true, false, true, 'allowance_expense'],
            ['T_KOMUNIKASI', 'Tunjangan komunikasi', 'earning', 'fixed_allowance', true, false, true, 'allowance_expense'],
            ['DED_KOPERASI', 'Iuran koperasi', 'deduction', 'deduction', false, false, false, 'other_deduction_payable'],
        ];
        foreach ($components as $i => [$code, $name, $type, $cat, $tax, $bpjs, $pro, $gl]) {
            PayComponent::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'category' => $cat, 'taxable' => $tax, 'bpjs_base' => $bpjs, 'prorated' => $pro, 'gl_key' => $gl, 'is_system' => false, 'sort_order' => ($i + 1) * 10]);
        }

        $grades = [
            ['STF-1', 'Staf Pelaksana', 'staff', 4000000, 9000000, false],
            ['STF-2', 'Staf Senior', 'staff', 7000000, 15000000, false],
            ['SPV', 'Supervisor', 'supervisor', 10000000, 20000000, false],
            ['MGR', 'Manajer', 'manager', 18000000, 40000000, false],
            ['EXE', 'Eksekutif (GM/VP)', 'executive', 35000000, 120000000, true],
            ['DIR', 'Direksi', 'director', 80000000, 500000000, true],
        ];
        foreach ($grades as [$code, $name, $tier, $min, $max, $dual]) {
            Grade::updateOrCreate(['code' => $code], ['name' => $name, 'tier' => $tier, 'min_salary' => $min, 'max_salary' => $max, 'requires_dual_approval' => $dual]);
        }

        foreach (['company.name' => config('app.name'), 'company.address' => '', 'company.npwp' => ''] as $k => $v) {
            if (Setting::find($k) === null) {
                Setting::put($k, $v);
            }
        }
    }
}
