<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->text('description')->nullable();
            // Piutang Usaha yang dipakai (biasanya satu akun kontrol, tapi dibuat fleksibel)
            $table->foreignId('receivable_account_id')->constrained('chart_of_accounts');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->enum('status', ['draft', 'unpaid', 'partial', 'paid', 'void'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            // Akun pendapatan yang akan dikredit untuk baris ini
            $table->foreignId('revenue_account_id')->constrained('chart_of_accounts');
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('cash_account_id')->constrained('chart_of_accounts'); // akun Kas/Bank penerima
            $table->string('note')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
