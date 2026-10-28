<?php

namespace Tests;

use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Halaman Inertia merender @vite; di tes tidak ada build aset.
        $this->withoutVite();
    }

    /** Buat user aktif dengan role (dan hak akses per orang bila staf). */
    protected function makeUser(string $role = 'staff', array $permissions = [], array $attributes = []): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::create(array_merge([
            'name' => ucfirst($role).' Uji',
            'email' => $role.'-'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password123',
            'email_verified_at' => now(),
            'status' => 'active',
            'role_label' => $role,
        ], $attributes));

        $user->assignRole($role);
        if ($permissions) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    protected function makeAccount(string $code, string $name, string $type, ?string $category = null, ?string $normal = null): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'category' => $category,
            'normal_balance' => $normal ?? (in_array($type, ['aset', 'beban'], true) ? 'debit' : 'kredit'),
            'is_active' => true,
            'is_header' => false,
        ]);
    }
}
