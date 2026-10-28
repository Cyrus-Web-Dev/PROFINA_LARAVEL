<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class MkpUser extends Command
{
    protected $signature = 'mkp:user {email : Email user}
                            {--name= : Nama (wajib bila user baru)}
                            {--password= : Password (wajib bila user baru; minimal 8 karakter)}
                            {--role=admin : admin (Manager) | staff | viewer}
                            {--position= : accounting | finance | pajak | payroll | lainnya (hanya untuk staff)}';

    protected $description = 'Buat user baru atau ubah role user yang sudah ada (tanpa perlu tinker / email OTP)';

    public function handle(): int
    {
        $role = (string) $this->option('role');
        if (! in_array($role, ['admin', 'staff', 'viewer'], true)) {
            $this->error('Role harus salah satu dari: admin, staff, viewer.');

            return self::FAILURE;
        }

        // Pastikan role & permission ada (aman dijalankan berulang).
        (new RolePermissionSeeder())->run();

        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = $this->option('name') ?: $this->ask('Nama lengkap');
            $password = $this->option('password') ?: $this->secret('Password (min. 8 karakter)');

            $validator = Validator::make(
                ['name' => $name, 'email' => $email, 'password' => $password],
                ['name' => 'required|string|max:255', 'email' => 'required|email', 'password' => 'required|string|min:8']
            );
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->error($message);
                }

                return self::FAILURE;
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // di-hash otomatis oleh cast "hashed"
                'email_verified_at' => now(),
            ]);
            $this->info("User baru dibuat: {$user->email}");
        }

        // User sudah ada + --password diberikan -> ganti password (berguna bila email lupa-password belum berfungsi).
        if ($user->wasRecentlyCreated === false && $this->option('password')) {
            if (strlen((string) $this->option('password')) < 8) {
                $this->error('Password minimal 8 karakter.');

                return self::FAILURE;
            }
            $user->forceFill(['password' => $this->option('password')])->save();
            $this->info('Password diperbarui.');
        }

        $user->syncRoles([$role]);

        $position = $this->option('position');
        if ($position && ! config("mkp.positions.{$position}")) {
            $this->error('Posisi tidak dikenal. Pilih: '.implode(', ', array_keys(config('mkp.positions'))));

            return self::FAILURE;
        }
        // Staf: hak akses awal mengikuti posisi. Selain staf: tidak ada hak akses per orang.
        if ($role === 'staff' && $position) {
            $user->syncPermissions(config("mkp.positions.{$position}.permissions", []));
        } elseif ($role !== 'staff') {
            $user->syncPermissions([]);
        }

        $user->forceFill(['role_label' => $role, 'status' => 'active', 'position' => $position ?: $user->position])->save();

        $this->info("Role untuk {$user->email} sekarang: {$role}");

        return self::SUCCESS;
    }
}
