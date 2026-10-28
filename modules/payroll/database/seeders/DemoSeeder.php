<?php

namespace Database\Seeders;

use App\Models\AttendanceSnapshot;
use App\Models\CompensationContract;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\PayComponent;
use App\Models\PayrollInput;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DATA CONTOH untuk mencoba PAYBRIDGE tanpa menyambung SDM:  php artisan db:seed --class=DemoSeeder
 * Sengaja menanam beberapa anomali agar mesin kontrol terlihat bekerja:
 *   - satu karyawan tanpa kontrak (NO_CONTRACT)  - dua karyawan berbagi rekening (DUP_BANK)
 *   - satu karyawan tanpa absensi sama sekali (GHOST_NO_ACTIVITY)  - satu rekening baru diubah (BANK_RECENT)
 * JANGAN dijalankan di production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }
        $this->call([RolePermissionSeeder::class, RegulationSeeder::class, MasterDataSeeder::class]);

        $depts = collect([['FIN', 'Keuangan'], ['OPS', 'Operasional'], ['HR', 'SDM'], ['IT', 'Teknologi Informasi']])
            ->mapWithKeys(fn ($d) => [$d[0] => Department::updateOrCreate(['code' => $d[0]], ['name' => $d[1]])]);
        $grade = fn ($c) => Grade::where('code', $c)->first();
        $comp = fn ($c) => PayComponent::where('code', $c)->first();

        // nip, nama, dept, jabatan, ptkp, npwp?, gaji, golongan, tunj. jabatan, rekening, lembur?
        $rows = [
            ['EMP-001', 'Budi Santoso', 'FIN', 'Staf Akuntansi', 'K/1', true, 8500000, 'STF-1', 1500000, '1234567801', false],
            ['EMP-002', 'Siti Rahmawati', 'FIN', 'Supervisor Keuangan', 'TK/0', true, 14000000, 'SPV', 2500000, '1234567802', false],
            ['EMP-003', 'Agus Pratama', 'OPS', 'Operator Gudang', 'K/2', true, 6200000, 'STF-1', 800000, '1234567803', true],
            ['EMP-004', 'Dewi Lestari', 'OPS', 'Staf Logistik', 'TK/0', false, 6800000, 'STF-1', 800000, '1234567804', true],
            ['EMP-005', 'Rizky Hidayat', 'IT', 'Software Engineer', 'TK/1', true, 15500000, 'STF-2', 2000000, '1234567805', false],
            ['EMP-006', 'Maya Anggraini', 'HR', 'Staf SDM', 'K/0', true, 7500000, 'STF-1', 1000000, '1234567806', false],
            ['EMP-007', 'Hendra Wijaya', 'IT', 'IT Manager', 'K/3', true, 32000000, 'MGR', 5000000, '1234567807', false],
            ['EMP-008', 'Fajar Nugroho', 'OPS', 'Kepala Operasional', 'K/2', true, 38000000, 'MGR', 6000000, '1234567808', false],
            ['EMP-009', 'Rina Kusuma', 'FIN', 'Staf Pajak', 'TK/0', true, 8000000, 'STF-1', 1000000, '1234567801', false],   // rekening SAMA dengan EMP-001
            ['EMP-010', 'Tono Saputra', 'OPS', 'Staf Admin', 'TK/0', true, 6000000, 'STF-1', 500000, '1234567810', false],     // tanpa absensi
            ['EMP-011', 'Lina Marlina', 'HR', 'Staf Rekrutmen', 'TK/0', true, 7000000, 'STF-1', 800000, '1234567811', false],  // tanpa kontrak
            ['EMP-012', 'Dr. Andini Prameswari', 'FIN', 'Direktur Keuangan', 'K/2', true, 95000000, 'DIR', 25000000, '1234567812', false], // golongan direksi
        ];

        $y = (int) now()->year;
        $m = (int) now()->month;
        foreach ($rows as [$nip, $name, $dept, $pos, $ptkp, $npwp, $salary, $g, $tj, $acct, $ot]) {
            $emp = Employee::updateOrCreate(['nip' => $nip], [
                'full_name' => $name, 'email' => strtolower(str_replace([' ', '.'], '', explode(' ', $name)[0])).'@contoh.test', 'department_id' => $depts[$dept]->id, 'position_title' => $pos,
                'employment_type' => 'tetap', 'employment_status' => 'active', 'join_date' => now()->subYears(2)->startOfMonth(), 'ptkp_status' => $ptkp,
                'npwp' => $npwp ? '09.254.294.3-407.000' : null, 'has_npwp' => $npwp, 'bank_name' => 'BCA', 'bank_account_number' => $acct, 'bank_account_hash' => Employee::hashAccount($acct),
                'bank_account_name' => $name, 'sdm_base_salary' => $salary, 'synced_at' => now(), 'birth_date' => '1990-05-17',
            ]);
            if ($nip === 'EMP-005') {
                $emp->update(['bank_changed_at' => now()->subDays(5)]);
            }

            if ($nip !== 'EMP-011' && ! $emp->activeContract()->exists()) {
                $this->contract($emp, $grade($g), $salary, [$comp('T_JABATAN')->id => $tj, $comp('T_TRANSPORT')->id => 500000], $ot);
            }
            if ($nip !== 'EMP-010') {
                AttendanceSnapshot::updateOrCreate(['employee_id' => $emp->id, 'year' => $y, 'month' => $m], [
                    'scheduled_days' => 22, 'present_days' => $nip === 'EMP-004' ? 20 : 22, 'alpha_days' => $nip === 'EMP-004' ? 2 : 0, 'fetched_at' => now(),
                    'details' => $ot ? [['date' => now()->startOfMonth()->addDays(2)->toDateString(), 'status' => 'present', 'check_in' => '08:00', 'check_out' => '20:00']] : null,
                ]);
            }
        }

        $agus = Employee::where('nip', 'EMP-003')->first();
        $dewi = Employee::where('nip', 'EMP-004')->first();
        PayrollInput::updateOrCreate(['employee_id' => $agus->id, 'type' => 'overtime', 'period_year' => $y, 'period_month' => $m, 'work_date' => now()->startOfMonth()->addDays(2)->toDateString()], [
            'description' => 'SPL-001 bongkar muat', 'day_type' => 'weekday', 'hours' => 3, 'amount' => 0, 'status' => 'approved', 'attendance_check' => ['ok' => true, 'message' => 'Sesuai absensi SDM (tercatat 3 jam).'],
        ]);
        PayrollInput::updateOrCreate(['employee_id' => $dewi->id, 'type' => 'bonus', 'period_year' => $y, 'period_month' => $m, 'description' => 'Bonus target Q3'], ['amount' => 10000000, 'status' => 'approved']);

        $this->command?->info('Data contoh dibuat. Buat putaran gaji bulan ini lalu "Hitung": akan muncul temuan NO_CONTRACT, DUP_BANK, GHOST_NO_ACTIVITY, BANK_RECENT, INPUT_ROUND, dsb.');
    }

    private function contract(Employee $emp, ?Grade $grade, float $salary, array $components, bool $ot): void
    {
        $terms = ['employee_id' => $emp->id, 'base_salary' => $salary, 'effective_date' => now()->subYear()->startOfYear()->toDateString(), 'end_date' => null, 'grade_id' => $grade?->id,
            'contract_type' => 'pkwtt', 'jkk_class' => 1, 'bpjs_kes_enrolled' => true, 'bpjs_tk_enrolled' => true, 'tax_scheme' => 'ter', 'thr_eligible' => true, 'overtime_eligible' => $ot];
        $comps = collect($components)->map(fn ($amt, $id) => ['pay_component_id' => $id, 'amount' => $amt])->values()->all();

        // Dibuat sebagai "proposed" dulu (komponen terkunci begitu kontrak aktif), lalu diaktifkan.
        $c = CompensationContract::create($terms + [
            'contract_number' => 'KTR-DEMO-'.str_pad((string) $emp->id, 5, '0', STR_PAD_LEFT), 'version' => 1, 'status' => 'proposed', 'source' => 'payroll',
            'proposed_by_name' => 'Seeder demo', 'reason' => 'Data contoh', 'terms_hash' => CompensationContract::hashTerms($terms, $comps),
        ]);
        foreach ($comps as $cc) {
            $c->components()->create($cc);
        }
        $c->update(['status' => 'active', 'activated_at' => now()]);
    }
}
