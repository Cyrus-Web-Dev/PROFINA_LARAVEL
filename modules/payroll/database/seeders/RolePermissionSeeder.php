<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Peran dirancang untuk PEMISAHAN TUGAS: pembuat (payroll-admin) -> pemeriksa (payroll-manager) -> penyetuju
 * (finance-approver) -> penyetuju kedua untuk gaji eksekutif (direksi). Auditor hanya membaca.
 */
class RolePermissionSeeder extends Seeder
{
    public static array $permissions = [
        'employees.view', 'sync.run', 'opening.manage',
        'contracts.view', 'contracts.propose', 'contracts.verify', 'contracts.approve', 'contracts.approve-executive',
        'components.manage', 'grades.manage', 'regulations.view', 'regulations.manage',
        'inputs.view', 'inputs.create', 'inputs.approve', 'inputs.approve-large',
        'loans.view', 'loans.manage', 'loans.approve',
        'runs.view', 'runs.create', 'runs.verify', 'runs.approve', 'runs.approve-executive', 'runs.hold', 'runs.disburse', 'runs.reconcile', 'runs.cancel',
        'findings.resolve', 'funding.view', 'remittances.view', 'remittances.manage',
        'budgets.view', 'budgets.manage', 'budgets.approve',
        'reports.view', 'reports.export', 'audit.view', 'integrations.manage', 'users.view', 'users.manage', 'settings.manage',
    ];

    public static function roles(): array
    {
        $read = ['employees.view', 'contracts.view', 'regulations.view', 'inputs.view', 'loans.view', 'runs.view', 'funding.view', 'remittances.view', 'budgets.view', 'reports.view'];

        return [
            'payroll-admin' => array_merge($read, ['sync.run', 'opening.manage', 'contracts.propose', 'inputs.create', 'loans.manage', 'runs.create', 'runs.disburse', 'budgets.manage', 'reports.export']),
            'payroll-manager' => array_merge($read, ['contracts.verify', 'components.manage', 'grades.manage', 'regulations.manage', 'inputs.approve', 'loans.approve', 'runs.verify', 'runs.hold', 'runs.reconcile', 'runs.cancel',
                'findings.resolve', 'remittances.manage', 'reports.export', 'audit.view', 'sync.run']),
            'finance-approver' => array_merge($read, ['contracts.approve', 'inputs.approve', 'inputs.approve-large', 'loans.approve', 'runs.approve', 'findings.resolve', 'budgets.approve', 'reports.export', 'audit.view']),
            'direksi' => array_merge($read, ['contracts.approve-executive', 'runs.approve-executive', 'budgets.approve', 'reports.export', 'audit.view']),
            'hr-partner' => ['employees.view', 'contracts.view', 'contracts.propose', 'reports.view'],
            'auditor' => array_merge($read, ['audit.view', 'reports.export']),
            'karyawan' => [],
        ];
    }

    public function run(): void
    {
        foreach (self::$permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'])->syncPermissions(self::$permissions);
        foreach (self::roles() as $name => $perms) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($perms);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
