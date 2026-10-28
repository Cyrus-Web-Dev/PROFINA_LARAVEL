<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MkpHashStatus extends Command
{
    protected $signature = 'mkp:hash-status';

    protected $description = 'Tampilkan algoritma hash password tiap user (pantau migrasi bcrypt -> Argon2id)';

    public function handle(): int
    {
        $this->info('Driver hash aktif: '.config('hashing.driver'));

        if (! defined('PASSWORD_ARGON2ID') && config('hashing.driver') === 'argon2id') {
            $this->error('PHP di server ini TIDAK mendukung Argon2id. Aktifkan/pasang PHP dengan libargon2 sebelum memakai driver ini.');

            return self::FAILURE;
        }

        $count = [];
        $stale = 0;

        User::query()->select(['id', 'password'])->orderBy('id')->each(function (User $user) use (&$count, &$stale) {
            $algo = password_get_info((string) $user->password)['algoName'] ?? 'unknown';
            $count[$algo] = ($count[$algo] ?? 0) + 1;

            if (Hash::needsRehash((string) $user->password)) {
                $stale++;
            }
        });

        if (! $count) {
            $this->line('Belum ada user.');

            return self::SUCCESS;
        }

        $this->table(['Algoritma', 'Jumlah user'], collect($count)->map(fn ($n, $a) => [$a, $n])->values()->all());
        $this->line("Perlu di-rehash saat login berikutnya: {$stale}");

        if ($stale === 0) {
            $this->info('Semua hash sudah sesuai config. Aman mengubah HASH_VERIFY=true di .env.');
        } else {
            $this->comment('Biarkan HASH_VERIFY=false sampai angka di atas 0 (atau minta user tersisa reset password).');
        }

        return self::SUCCESS;
    }
}
