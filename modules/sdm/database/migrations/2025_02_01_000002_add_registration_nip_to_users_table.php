<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // NIP yang diisi calon pengguna saat mendaftar sendiri; dipakai Admin HR untuk mencocokkan
            // akun dengan data karyawan saat menyetujui pendaftaran.
            $table->string('registration_nip', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registration_nip');
        });
    }
};
