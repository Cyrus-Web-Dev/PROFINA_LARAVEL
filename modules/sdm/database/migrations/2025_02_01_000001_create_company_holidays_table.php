<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 150);
            // holiday = libur tambahan khusus perusahaan (mis. cuti bersama internal, ulang tahun perusahaan)
            // working_day = MEMAKSA tanggal ini tetap hari kerja walau di kalender nasional tercatat libur
            $table->string('type', 20)->default('holiday');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_holidays');
    }
};
