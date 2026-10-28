<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_review_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // mis. "Semester 1 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open, closed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_cycles');
    }
};
