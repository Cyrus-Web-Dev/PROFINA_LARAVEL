<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SdmUser extends Command
{
    protected $signature = 'sdm:user {email : Email user}
                            {--name= : Nama (wajib bila user baru)}
                            {--password= : Password (wajib bila user baru; minimal 8 karakter)}
                            {--role=karyawan : super-admin | hr-admin | manajer | karyawan}';

    protected $description = 'Buat user baru atau ubah role user yang sudah ada (tanpa perlu tinker / email OTP)';

    public function handle(): int
    {
        $role = (string) $this->option('role');
        $validRoles = ['super-admin', 'hr-admin', 'manajer', 'karyawan'];
        if (! in_array($role, $validRoles, true)) {
            $this->error('Role harus salah satu dari: '.implode(', ', $validRoles));

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
                'status' => 'active',
            ]);
            $this->info("User baru dibuat: {$user->email}");
        }

        // User sudah ada + --password diberikan -> ganti password (berguna bila lupa-password belum berfungsi).
        if ($user->wasRecentlyCreated === false && $this->option('password')) {
            if (strlen((string) $this->option('password')) < 8) {
                $this->error('Password minimal 8 karakter.');

                return self::FAILURE;
            }
            $user->forceFill(['password' => $this->option('password')])->save();
            $this->info('Password diperbarui.');
        }

        $user->syncRoles([$role]);
        $user->forceFill(['status' => 'active'])->save();

        $this->info("Role untuk {$user->email} sekarang: {$role}");

        return self::SUCCESS;
    }
}
