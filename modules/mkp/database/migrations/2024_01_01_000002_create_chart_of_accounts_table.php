<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // contoh: 1-1000, 4-1000, dst
            $table->string('name');
            // Kelompok akun sesuai kaidah akuntansi baku
            $table->enum('type', ['aset', 'kewajiban', 'ekuitas', 'pendapatan', 'beban']);
            // Sub-kelompok untuk klasifikasi laporan (aset lancar, aset tetap, dst)
            $table->string('category', 50)->nullable();
            // Saldo normal akun: debit atau kredit -> menentukan logika penambahan/pengurangan saldo
            $table->enum('normal_balance', ['debit', 'kredit']);
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            // Akun header (kelompok) tidak boleh dipakai langsung untuk jurnal
            $table->boolean('is_header')->default(false);
            $table->timestamps();

            $table->index(['type', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
