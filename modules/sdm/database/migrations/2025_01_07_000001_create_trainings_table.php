<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('trainer', 150)->nullable();
            $table->string('provider', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location', 150)->nullable();
            $table->unsignedSmallInteger('quota')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, ongoing, completed, cancelled
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
