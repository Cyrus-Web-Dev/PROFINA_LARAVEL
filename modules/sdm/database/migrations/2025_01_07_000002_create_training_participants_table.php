<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('status', 20)->default('registered'); // registered, attended, absent, completed
            $table->string('certificate_path')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['training_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};
