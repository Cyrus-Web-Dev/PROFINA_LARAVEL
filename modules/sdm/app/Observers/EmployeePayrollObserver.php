<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\PayrollBridge;

/**
 * Mengabari PAYBRIDGE saat data karyawan berubah. PENTING: perubahan gaji pokok di SDM TIDAK langsung berlaku untuk payroll;
 * ia menjadi USULAN kontrak di PAYBRIDGE (verifikasi Payroll -> persetujuan Keuangan). Untuk menghindari pengulangan tak
 * berujung, perubahan yang berasal dari PAYBRIDGE sendiri (kontrak aktif) diterapkan lewat self::silently().
 */
class EmployeePayrollObserver
{
    private static int $silent = 0;

    public static function silently(callable $fn): mixed
    {
        self::$silent++;
        try {
            return $fn();
        } finally {
            self::$silent--;
        }
    }

    private const BANK = ['bank_name', 'bank_account_number', 'bank_account_name'];

    private const STATUS = ['employment_status', 'resign_date'];

    private const GENERAL = ['full_name', 'department_id', 'position_id', 'ptkp_status', 'marital_status', 'dependents_count', 'npwp', 'bpjs_kesehatan_number', 'bpjs_tk_number',
        'employment_type', 'contract_end_date', 'join_date', 'date_of_birth', 'phone', 'personal_email'];

    public function __construct(private PayrollBridge $bridge)
    {
    }

    public function created(Employee $e): void
    {
        if (self::$silent) {
            return;
        }
        $this->bridge->dispatch('employee.created', ['nip' => $e->nip]);
    }

    public function updated(Employee $e): void
    {
        if (self::$silent) {
            return;
        }
        $changed = array_keys($e->getChanges());
        if (! $changed) {
            return;
        }
        $nip = $e->nip;

        if (in_array('base_salary', $changed, true)) {
            $effective = now()->day === 1 ? now()->startOfDay() : now()->addMonthNoOverflow()->startOfMonth();
            $this->bridge->dispatch('employee.compensation_changed', [
                'nip' => $nip, 'old_base_salary' => (float) $e->getOriginal('base_salary'), 'new_base_salary' => (float) $e->base_salary,
                'effective_date' => $effective->toDateString(), 'contract_end_date' => $e->contract_end_date?->toDateString(),
                'changed_by' => auth()->user()?->name ?? 'sistem', 'reason' => 'Perubahan gaji pokok di SDM',
            ]);
        }
        if (array_intersect(self::STATUS, $changed)) {
            $this->bridge->dispatch('employee.status_changed', ['nip' => $nip, 'employment_status' => $e->employment_status, 'resign_date' => $e->resign_date?->toDateString()]);
        }
        if (array_intersect(self::BANK, $changed)) {
            $this->bridge->dispatch('employee.bank_changed', ['nip' => $nip]);
        }
        if ($fields = array_values(array_intersect(self::GENERAL, $changed))) {
            $this->bridge->dispatch('employee.updated', ['nip' => $nip, 'fields' => $fields]);
        }
    }
}
