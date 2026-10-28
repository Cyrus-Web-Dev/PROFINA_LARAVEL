<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();

            $table->decimal('basic_salary', 14, 2)->default(0);
            // Tunjangan & potongan lain-lain, bentuk fleksibel: [{"label":"Tunjangan Transport","amount":500000}, ...]
            $table->jsonb('allowances')->nullable();
            $table->jsonb('other_deductions')->nullable();
            $table->decimal('overtime_amount', 14, 2)->default(0);

            // Komponen BPJS Kesehatan (default 1% karyawan / 4% perusahaan dari upah, maks. batas upah yang berlaku)
            $table->decimal('bpjs_kesehatan_employee', 14, 2)->default(0);
            $table->decimal('bpjs_kesehatan_company', 14, 2)->default(0);
            // BPJS Ketenagakerjaan: JHT, JKK, JKM, JP
            $table->decimal('bpjs_jht_employee', 14, 2)->default(0);
            $table->decimal('bpjs_jht_company', 14, 2)->default(0);
            $table->decimal('bpjs_jkk_company', 14, 2)->default(0);
            $table->decimal('bpjs_jkm_company', 14, 2)->default(0);
            $table->decimal('bpjs_jp_employee', 14, 2)->default(0);
            $table->decimal('bpjs_jp_company', 14, 2)->default(0);

            $table->decimal('pph21', 14, 2)->default(0);

            $table->decimal('gross_salary', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_salary', 14, 2)->default(0);

            $table->string('status', 20)->default('draft'); // draft, paid
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable(); // transfer, tunai
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
