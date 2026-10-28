<?php

namespace App\Console\Commands;

use App\Models\ModuleAssignment;
use App\Models\User;
use App\Services\ModuleCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;

class PortalUser extends Command
{
    protected $signature = 'profina:user {email} {--name=} {--password=} {--core-role= : super-admin|admin-portal|pemantau} {--assign=* : modul:role, mis. marketing:sales}';

    protected $description = 'Buat/ubah akun Portal lewat terminal (akun pertama, reset darurat, penugasan cepat)';

    public function handle(): int
    {
        (new RolePermissionSeeder)->run();

        $email = strtolower($this->argument('email'));
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! $this->option('password')) {
                $this->error('Akun baru butuh --password.');

                return self::FAILURE;
            }
            $user = User::create(['name' => $this->option('name') ?: $email, 'email' => $email, 'password' => $this->option('password'), 'status' => 'active', 'email_verified_at' => now()]);
            $this->info("Akun {$email} dibuat.");
        } elseif ($this->option('password')) {
            $user->forceFill(['password' => $this->option('password'), 'status' => 'active'])->save();
            $this->info('Password diperbarui.');
        }

        if ($role = $this->option('core-role')) {
            if (! array_key_exists($role, config('portal.core_roles'))) {
                $this->error('core-role tidak dikenal.');

                return self::FAILURE;
            }
            $user->syncRoles([$role]);
            $this->info("Peran Portal: {$role}");
        }

        foreach ($this->option('assign') as $pair) {
            [$module, $role] = array_pad(explode(':', $pair, 2), 2, null);
            $m = ModuleCatalog::find((string) $module);
            if (! $m || ! array_key_exists((string) $role, $m['roles'])) {
                $this->error("Penugasan tidak valid: {$pair}");

                return self::FAILURE;
            }
            ModuleAssignment::updateOrCreate(['user_id' => $user->id, 'module' => $module], ['role' => $role]);
            $this->info("Penugasan: {$module} sebagai {$role}");
        }

        return self::SUCCESS;
    }
}
