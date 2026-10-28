<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year');
            // draft (bisa diubah) -> processed (dihitung, terkunci) -> paid (sudah dibayar)
            $table->string('status', 20)->default('draft');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['period_month', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
