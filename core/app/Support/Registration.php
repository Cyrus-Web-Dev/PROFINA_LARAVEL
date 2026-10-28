<?php

namespace App\Support;

use App\Models\User;

class Registration
{
    /**
     * Pendaftaran mandiri terbuka kecuali PROFINA_REGISTRATION_ENABLED=false. Selalu boleh bila belum ada satu pun
     * user (pendaftar pertama otomatis menjadi super-admin). Akun berikutnya berstatus "pending" sampai
     * disetujui Admin PROFINA (dan dihubungkan ke data karyawan) di menu Kelola Pengguna.
     */
    public static function allowed(): bool
    {
        return (bool) config('portal.registration_enabled') || ! User::query()->exists();
    }
}
