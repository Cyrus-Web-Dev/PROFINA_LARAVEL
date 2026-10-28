<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrak kompensasi: satu-satunya sumber gaji yang boleh dibayar. Versi baru = baris baru (riwayat tidak pernah ditimpa).
 * Setelah disetujui, isi kontrak dikunci oleh trigger database (lihat migrasi audit_ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_contracts', function (Blueprint $t) {
            $t->id();
            $t->string('contract_number', 30)->unique();
            $t->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $t->unsignedInteger('version')->default(1);
            $t->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $t->string('contract_type', 20)->default('pkwt');        // pkwt | pkwtt | magang | harian | direksi
            $t->decimal('base_salary', 18, 2);
            $t->date('effective_date');
            $t->date('end_date')->nullable();
            $t->date('superseded_on')->nullable();                    // diisi sistem saat digantikan versi baru (TIDAK ikut hash isi kontrak)
            // proposed -> verified -> approved -> active -> superseded|expired ; rejected | cancelled
            $t->string('status', 16)->default('proposed')->index();
            $t->string('source', 12)->default('payroll');             // sdm | payroll
            $t->string('sdm_ref', 80)->nullable();
            $t->string('proposed_by_name', 120)->nullable();
            $t->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason')->nullable();

            $t->unsignedTinyInteger('jkk_class')->default(1);          // kelas risiko JKK 1..5
            $t->boolean('bpjs_kes_enrolled')->default(true);
            $t->boolean('bpjs_tk_enrolled')->default(true);
            $t->string('tax_scheme', 12)->default('ter');             // ter | exempt
            $t->boolean('thr_eligible')->default(true);
            $t->boolean('overtime_eligible')->default(false);

            $t->string('document_ref', 120)->nullable();              // nomor/ID dokumen kontrak di SDM
            $t->string('document_sha256', 64)->nullable();
            $t->text('notes')->nullable();
            $t->string('terms_hash', 64);                             // SHA-256 atas isi kontrak (deteksi perubahan diam-diam)

            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('verification_note')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('second_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('second_approved_at')->nullable();
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->text('rejection_note')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->timestamps();

            $t->unique(['employee_id', 'version']);
            $t->index(['employee_id', 'effective_date']);
        });

        Schema::create('contract_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('contract_id')->constrained('compensation_contracts')->cascadeOnDelete();
            $t->foreignId('pay_component_id')->constrained('pay_components')->restrictOnDelete();
            $t->decimal('amount', 18, 2);
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'pay_component_id']);
        });

        // Rantai persetujuan generik (kontrak, anggaran, input besar, ...).
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->string('approvable_type', 60);
            $t->unsignedBigInteger('approvable_id');
            $t->unsignedTinyInteger('step');
            $t->string('permission', 60);               // izin yang dibutuhkan untuk memutuskan langkah ini
            $t->string('label', 120);
            $t->string('status', 12)->default('pending'); // pending | approved | rejected
            $t->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('acted_name', 120)->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->text('note')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
            $t->index(['approvable_type', 'approvable_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            // Hanya BOLEH ada satu kontrak aktif per karyawan (dijamin database, bukan hanya aplikasi).
            DB::statement("CREATE UNIQUE INDEX uq_contract_one_active ON compensation_contracts (employee_id) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('contract_components');
        Schema::dropIfExists('compensation_contracts');
    }
};
