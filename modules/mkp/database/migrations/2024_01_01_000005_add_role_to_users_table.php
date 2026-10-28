<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Catatan: role granular (permission per aksi) ditangani oleh spatie/laravel-permission.
    // Kolom ini hanya label cepat untuk tampilan UI (badge role di navbar, dsb).
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_label', 30)->default('accountant')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role_label');
        });
    }
};
