<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ChartOfAccountSeeder::class,
        ]);

        // Opsional: buat admin awal dari .env (MKP_ADMIN_EMAIL + MKP_ADMIN_PASSWORD).
        // Berguna supaya kamu bisa login pertama kali tanpa harus registrasi + email OTP.
        $email = config('mkp.admin.email');
        $password = config('mkp.admin.password');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => config('mkp.admin.name'), 'password' => $password, 'email_verified_at' => now()]
            );
            $admin->syncRoles(['admin']);
            $admin->forceFill(['role_label' => 'admin'])->save();
        }
    }
}
