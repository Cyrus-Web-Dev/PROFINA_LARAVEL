<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug')->unique();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->string('employment_type', 20)->default('kontrak');
            $table->text('description');
            $table->text('requirements');
            $table->unsignedSmallInteger('slots')->default(1);
            $table->string('status', 20)->default('open')->index(); // open, closed
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('opens_at')->nullable();
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
    }
};
