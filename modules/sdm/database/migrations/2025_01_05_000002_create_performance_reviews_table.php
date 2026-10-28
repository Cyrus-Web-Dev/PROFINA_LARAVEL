<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_review_cycles')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            // Target/KPI terstruktur: [{"indicator":"Penjualan bulanan","target":100,"achieved":92,"weight":40}, ...]
            $table->jsonb('kpi_items')->nullable();
            $table->decimal('final_score', 5, 2)->nullable(); // 0-100
            $table->string('rating', 5)->nullable(); // A / B / C / D / E
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('employee_comment')->nullable();
            $table->string('status', 20)->default('draft'); // draft, submitted, acknowledged
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['cycle_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
