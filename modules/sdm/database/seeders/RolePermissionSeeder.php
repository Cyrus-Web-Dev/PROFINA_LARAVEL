<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Peta permission per modul. Format aksi: view (lihat), manage (tambah/ubah/hapus),
     * approve (menyetujui, khusus modul yg butuh approval berjenjang), export (unduh laporan).
     */
    public static array $modules = [
        'employees' => ['view', 'manage', 'export'],
        'departments' => ['view', 'manage'],
        'attendances' => ['view', 'manage'],
        'leaves' => ['view', 'manage', 'approve'],
        'payroll' => ['view', 'manage', 'approve'],
        'performance' => ['view', 'manage'],
        'recruitment' => ['view', 'manage'],
        'trainings' => ['view', 'manage'],
        'documents' => ['view', 'manage'],
        'announcements' => ['view', 'manage'],
        'holidays' => ['view', 'manage'], // hari libur perusahaan (melengkapi data libur nasional dari API)
        'reports' => ['view', 'export'],
        'users' => ['view', 'manage'], // kelola akun login & role
        'integrations' => ['manage'], // klien API & webhook (PAYBRIDGE) - hanya super-admin
        'settings' => ['manage'],
    ];

    public function run(): void
    {
        $permissions = [];
        foreach (self::$modules as $module => $actions) {
            foreach ($actions as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // super-admin: akses penuh ke semua permission di atas.
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        // hr-admin: mengelola operasional HR sehari-hari, TIDAK termasuk pengaturan sistem.
        $hrAdmin = Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
        $hrAdmin->syncPermissions(array_filter($permissions, fn ($p) => ! str_starts_with($p, 'settings.') && ! str_starts_with($p, 'integrations.')));

        // manajer: melihat/menilai/menyetujui HANYA tim bawahannya (pembatasan tim dilakukan di controller
        // lewat App\Support\TeamScope, BUKAN dari permission ini). TIDAK boleh melihat gaji/payroll &
        // dokumen pribadi seluruh karyawan.
        $manajer = Role::firstOrCreate(['name' => 'manajer', 'guard_name' => 'web']);
        $manajer->syncPermissions([
            'employees.view', 'departments.view', 'attendances.view',
            'leaves.view', 'leaves.approve', 'performance.view', 'performance.manage',
            'trainings.view', 'announcements.view', 'holidays.view', 'reports.view',
        ]);

        // karyawan: SELF-SERVICE saja. Sengaja TIDAK diberi izin "*.view" modul manapun, karena izin itu
        // membuka halaman ADMIN yang berisi data seluruh karyawan. Halaman self-service (absen saya, cuti
        // saya, slip gaji saya, dst) tidak butuh izin khusus - cukup login + terhubung ke data karyawan.
        $karyawan = Role::firstOrCreate(['name' => 'karyawan', 'guard_name' => 'web']);
        $karyawan->syncPermissions(['announcements.view', 'holidays.view']);
    }
}
