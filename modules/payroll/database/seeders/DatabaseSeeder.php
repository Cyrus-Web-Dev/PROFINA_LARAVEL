<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, RegulationSeeder::class, MasterDataSeeder::class]);

        // Akun super-admin awal dari .env (PAY_ADMIN_EMAIL + PAY_ADMIN_PASSWORD)
        $email = config('pay.admin.email');
        $password = config('pay.admin.password');
        if ($email && $password) {
            $admin = User::firstOrCreate(['email' => $email], ['name' => config('pay.admin.name'), 'password' => $password, 'email_verified_at' => now(), 'status' => 'active']);
            $admin->syncRoles(['super-admin']);
        }
    }
}
