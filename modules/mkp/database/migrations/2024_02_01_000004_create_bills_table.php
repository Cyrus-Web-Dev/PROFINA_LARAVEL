<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 30)->unique();
            $table->foreignId('vendor_id')->constrained();
            $table->date('bill_date');
            $table->date('due_date');
            $table->text('description')->nullable();
            $table->foreignId('payable_account_id')->constrained('chart_of_accounts');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->enum('status', ['draft', 'unpaid', 'partial', 'paid', 'void'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            // Akun beban/persediaan yang akan didebit untuk baris ini
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('cash_account_id')->constrained('chart_of_accounts');
            $table->string('note')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
    }
};
