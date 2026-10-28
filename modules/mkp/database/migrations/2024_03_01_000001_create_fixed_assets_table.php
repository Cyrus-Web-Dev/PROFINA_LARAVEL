<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number', 30)->unique();
            $table->string('name');
            $table->string('category')->nullable(); // Tanah, Bangunan, Kendaraan, Peralatan, dst
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0); // nilai residu
            $table->unsignedInteger('useful_life_months'); // umur ekonomis dalam bulan
            $table->enum('depreciation_method', ['straight_line'])->default('straight_line');
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts'); // akun aset tetap (debit saat perolehan)
            $table->foreignId('accumulated_depreciation_account_id')->constrained('chart_of_accounts'); // akun akumulasi depresiasi (kredit)
            $table->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts'); // akun beban depresiasi (debit tiap periode)
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->unsignedInteger('months_depreciated')->default(0);
            $table->enum('status', ['active', 'fully_depreciated', 'disposed'])->default('active');
            $table->foreignId('acquisition_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->date('period_date'); // akhir bulan periode depresiasi
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
