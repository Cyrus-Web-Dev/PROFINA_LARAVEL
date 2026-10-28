<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penugasan: satu user bisa punya beberapa modul, tetapi hanya SATU role per modul.
        Schema::create('module_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 30);
            $table->string('role', 60);
            $table->json('permissions')->nullable(); // hak tambahan per orang (dipakai MKP)
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'module']);
        });

        // Setiap kali user dibawa masuk ke sebuah modul tercatat satu sesi SSO. Modul menanyakan sesi ini
        // secara berkala (introspeksi), sehingga pencabutan akses/logout di Portal langsung berlaku.
        Schema::create('sso_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 30)->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revoked_reason', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_sessions');
        Schema::dropIfExists('module_assignments');
    }
};
