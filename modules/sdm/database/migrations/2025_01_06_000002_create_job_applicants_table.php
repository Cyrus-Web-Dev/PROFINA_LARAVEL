<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_vacancy_id')->constrained('job_vacancies')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('resume_path')->nullable();
            $table->text('cover_letter')->nullable();
            // applied, screening, interview, offer, hired, rejected
            $table->string('stage', 20)->default('applied')->index();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 50)->nullable(); // referral, linkedin, jobstreet, dll
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applicants');
    }
};
