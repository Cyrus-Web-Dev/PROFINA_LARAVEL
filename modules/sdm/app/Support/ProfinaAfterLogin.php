<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Setelah masuk lewat Portal: hubungkan akun ke data karyawan (untuk layanan mandiri) bila belum terhubung.
 * Pencocokan: NIP yang tercatat di akun Portal. Bila tidak cocok, Admin HR menautkannya manual
 * di menu Kelola Pengguna (perilaku lama tidak berubah).
 */
class ProfinaAfterLogin
{
    public function __invoke($user, array $claims): void
    {
        // Query langsung (bukan model Employee) supaya tidak terkena scope tim/peran milik user yang baru masuk.
        if (empty($claims['nip']) || DB::table('employees')->where('user_id', $user->id)->exists()) {
            return;
        }

        DB::table('employees')->where('nip', $claims['nip'])->whereNull('user_id')->update(['user_id' => $user->id]);
    }
}
