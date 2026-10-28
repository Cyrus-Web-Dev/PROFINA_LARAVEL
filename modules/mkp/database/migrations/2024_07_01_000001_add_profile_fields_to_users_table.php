<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('employee_id', 50)->nullable();   // NIP / nomor induk pegawai
            $table->string('job_title', 100)->nullable();    // jabatan
            $table->string('department', 100)->nullable();   // divisi / departemen
            $table->date('birth_date')->nullable();
            $table->text('address')->nullable();
            $table->text('bio')->nullable();
            $table->json('education')->nullable();           // riwayat pendidikan: [{level, institution, major, start_year, end_year}]
            $table->string('avatar_path')->nullable();       // path foto di storage/app/private/avatars
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'phone', 'employee_id', 'job_title', 'department', 'birth_date', 'address', 'bio', 'education', 'avatar_path']);
        });
    }
};
