<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role harus sama dengan katalog di Portal (core/config/portal.php -> modules.marketing.roles).
 * scope.all = boleh melihat data SEMUA sales; tanpa izin ini sales hanya melihat pelanggan/penawaran miliknya.
 * cost.view = boleh melihat HPP (rahasia dapur) & margin. Hanya marketing-manager.
 */
class RolePermissionSeeder extends Seeder
{
    public static array $permissions = [
        'customers.view', 'customers.manage',
        'products.view', 'products.manage', 'prices.manage', 'prices.override', 'cost.view',
        'quotations.view', 'quotations.create', 'quotations.approve',
        'orders.view', 'orders.manage',
        'deliveries.view', 'deliveries.manage',
        'complaints.view', 'complaints.manage',
        'reports.view', 'scope.all',
    ];

    public static function roles(): array
    {
        $view = ['customers.view', 'products.view', 'quotations.view', 'orders.view', 'deliveries.view', 'complaints.view'];

        return [
            'marketing-manager' => self::$permissions,
            'sales-admin' => array_merge($view, ['customers.manage', 'products.manage', 'prices.manage', 'quotations.create', 'quotations.approve', 'orders.manage', 'deliveries.manage', 'complaints.manage', 'reports.view', 'scope.all']),
            'sales' => array_merge($view, ['customers.manage', 'quotations.create', 'complaints.manage']),
            'viewer' => array_merge($view, ['reports.view', 'scope.all']),
        ];
    }

    public function run(): void
    {
        foreach (self::$permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        foreach (self::roles() as $name => $perms) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
