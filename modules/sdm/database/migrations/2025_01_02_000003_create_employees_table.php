<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nip', 30)->unique(); // Nomor Induk Pegawai
            $table->string('full_name', 150);
            $table->enum('gender', ['L', 'P']);
            $table->string('place_of_birth', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('personal_email', 150)->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->default('single');
            $table->unsignedTinyInteger('dependents_count')->default(0); // jumlah tanggungan (utk PTKP)

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();

            // tetap = PKWTT, kontrak = PKWT, magang, harian = harian lepas
            $table->enum('employment_type', ['tetap', 'kontrak', 'magang', 'harian'])->default('kontrak');
            $table->date('join_date');
            $table->date('contract_end_date')->nullable();
            $table->date('resign_date')->nullable();
            // active, resigned, terminated, retired
            $table->enum('employment_status', ['active', 'resigned', 'terminated', 'retired'])->default('active');

            $table->decimal('base_salary', 14, 2)->default(0);
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 150)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('bpjs_kesehatan_number', 30)->nullable();
            $table->string('bpjs_tk_number', 30)->nullable();
            $table->enum('ptkp_status', ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'])->default('TK/0');

            $table->string('photo_path')->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employment_status', 'department_id']);
            $table->index('full_name');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_employee_id']);
        });
        Schema::dropIfExists('employees');
    }
};
