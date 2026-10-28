<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 30)->unique(); // JU-2026-00001
            $table->date('journal_date');
            $table->foreignId('fiscal_period_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['umum', 'penyesuaian', 'penutup', 'pembalik'])->default('umum');
            $table->string('reference', 100)->nullable(); // no. faktur/invoice/bukti
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'posted', 'void'])->default('draft');
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['journal_date', 'status']);
        });

        Schema::create('journal_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description')->nullable();
            $table->unsignedInteger('line_order')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_details');
        Schema::dropIfExists('journals');
    }
};
