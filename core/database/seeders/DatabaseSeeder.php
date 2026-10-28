<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $email = config('portal.admin.email');
        $password = config('portal.admin.password');

        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => config('portal.admin.name'), 'password' => $password, 'email_verified_at' => now(), 'status' => 'active']
            );
            $admin->syncRoles(['super-admin']);
        }
    }
}
