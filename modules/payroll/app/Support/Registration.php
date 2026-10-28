<?php

namespace App\Support;

use App\Models\User;

class Registration
{
    /**
     * Pendaftaran mandiri terbuka kecuali PAY_REGISTRATION_ENABLED=false. Selalu boleh bila belum ada satu pun
     * user (pendaftar pertama otomatis menjadi super-admin). Akun berikutnya berstatus "pending" sampai
     * disetujui Admin Payroll (dan dihubungkan ke data karyawan) di menu Kelola Pengguna.
     */
    public static function allowed(): bool
    {
        return (bool) config('pay.registration_enabled') || ! User::query()->exists();
    }
}
