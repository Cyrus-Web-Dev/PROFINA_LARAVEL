<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role di sistem:  admin (tampil sebagai "Manager"), staff, viewer.
 * Hak akses staf diberikan PER ORANG (lihat halaman Kelola Pengguna), bukan lewat role.
 * Aman dijalankan berulang.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'coa.manage', 'journal.create', 'journal.post', 'journal.void', 'journal.approve',
            'ar.manage', 'ap.manage', 'cashbank.manage', 'fixed-asset.manage', 'budget.manage',
            'bank-reconciliation.manage', 'procurement.decide', 'procurement.disburse', 'payroll.decide', 'payroll.disburse', 'reports.view', 'users.manage',
        ];
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        $this->migrateLegacyAccountantRole();

        Role::firstOrCreate(['name' => 'admin'])->syncPermissions($permissions);
        Role::firstOrCreate(['name' => 'staff'])->syncPermissions(['reports.view']);
        Role::firstOrCreate(['name' => 'viewer'])->syncPermissions(['reports.view']);
    }

    /**
     * Versi lama memakai role "accountant" (hak akses melekat di role). Sekarang menjadi "staff"
     * dengan hak akses per orang: hak lama disalin ke tiap user supaya tidak ada yang kehilangan akses.
     */
    private function migrateLegacyAccountantRole(): void
    {
        $old = Role::where('name', 'accountant')->first();
        if (! $old) {
            return;
        }

        $perms = $old->permissions->pluck('name')->all();
        Role::firstOrCreate(['name' => 'staff']);

        foreach (User::role('accountant')->get() as $user) {
            $user->givePermissionTo($perms);
            $user->removeRole('accountant');
            $user->assignRole('staff');
            $user->forceFill(['role_label' => 'staff'])->save();
        }

        $old->delete();
    }
}
