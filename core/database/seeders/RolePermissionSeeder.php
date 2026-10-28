<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public static array $permissions = ['users.view', 'users.manage', 'monitor.view', 'audit.view', 'sessions.revoke'];

    public function run(): void
    {
        foreach (self::$permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web'])->syncPermissions(self::$permissions);
        Role::firstOrCreate(['name' => 'admin-portal', 'guard_name' => 'web'])->syncPermissions(['users.view', 'users.manage', 'monitor.view', 'audit.view', 'sessions.revoke']);
        Role::firstOrCreate(['name' => 'pemantau', 'guard_name' => 'web'])->syncPermissions(['monitor.view', 'audit.view']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
