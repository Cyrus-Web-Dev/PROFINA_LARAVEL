<?php

namespace App\Support;

use App\Models\User;

class Registration
{
    /**
     * Pendaftaran mandiri terbuka kecuali SDM_REGISTRATION_ENABLED=false. Selalu boleh bila belum ada satu pun
     * user (pendaftar pertama otomatis menjadi super-admin). Akun berikutnya berstatus "pending" sampai
     * disetujui Admin HR (dan dihubungkan ke data karyawan) di menu Kelola Pengguna.
     */
    public static function allowed(): bool
    {
        return (bool) config('hr.registration_enabled') || ! User::query()->exists();
    }
}
