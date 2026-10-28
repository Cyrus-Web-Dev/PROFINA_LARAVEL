<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            LeaveTypeSeeder::class,
            DepartmentSeeder::class,
        ]);

        // Opsional: buat akun admin awal dari .env (SDM_ADMIN_EMAIL + SDM_ADMIN_PASSWORD).
        // Berguna supaya kamu bisa login pertama kali tanpa proses undangan.
        $email = config('hr.admin.email');
        $password = config('hr.admin.password');

        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => config('hr.admin.name'),
                    'password' => $password,
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );
            $admin->syncRoles(['super-admin']);

            if (! $admin->employee) {
                $hrManagerPosition = Position::where('title', 'Manajer HR')->first();

                Employee::firstOrCreate(
                    ['user_id' => $admin->id],
                    [
                        'nip' => 'ADM-0001',
                        'full_name' => $admin->name,
                        'gender' => 'L',
                        'department_id' => $hrManagerPosition?->department_id,
                        'position_id' => $hrManagerPosition?->id,
                        'employment_type' => 'tetap',
                        'join_date' => now()->toDateString(),
                        'employment_status' => 'active',
                        'base_salary' => 0,
                        'ptkp_status' => 'TK/0',
                    ]
                );
            }
        }
    }
}
