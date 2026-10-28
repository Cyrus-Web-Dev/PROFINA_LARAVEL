<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            // contract, id_card, diploma, certificate, npwp, bpjs, other
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
