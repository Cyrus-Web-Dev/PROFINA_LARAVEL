<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data induk PAYBRIDGE. Data karyawan di sini adalah CERMIN dari SDM (sumber kebenaran = SDM):
 * PAYBRIDGE tidak pernah mengubah data kepegawaian, hanya membaca & menyimpan salinan untuk perhitungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sdm_id')->nullable()->unique();
            $t->string('code', 30)->unique();
            $t->string('name', 120);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // Golongan/grade gaji: batas bawah-atas gaji pokok per golongan. Kontrak di luar rentang butuh persetujuan lebih tinggi.
        Schema::create('grades', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name', 80);
            $t->string('tier', 20)->default('staff');            // staff | supervisor | manager | executive | director
            $t->decimal('min_salary', 18, 2)->default(0);
            $t->decimal('max_salary', 18, 2)->default(0);
            $t->boolean('requires_dual_approval')->default(false); // true utk eksekutif/direksi
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sdm_id')->nullable()->unique();
            $t->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $t->string('nip', 30)->unique();
            $t->string('full_name', 150);
            $t->string('gender', 1)->nullable();
            $t->text('birth_date')->nullable();                    // terenkripsi (cast), format Y-m-d
            $t->string('email', 150)->nullable();
            $t->string('phone', 30)->nullable();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('position_title', 120)->nullable();
            $t->string('employment_type', 20)->default('kontrak');   // tetap | kontrak | magang | harian
            $t->string('employment_status', 20)->default('active');  // active | resigned | terminated | retired
            $t->date('join_date')->nullable();
            $t->date('contract_end_date')->nullable();
            $t->date('resign_date')->nullable();
            $t->string('ptkp_status', 10)->default('TK/0');
            $t->text('npwp')->nullable();                           // terenkripsi
            $t->boolean('has_npwp')->default(false);
            $t->string('bank_name', 60)->nullable();
            $t->text('bank_account_number')->nullable();            // terenkripsi
            $t->string('bank_account_hash', 64)->nullable()->index(); // HMAC-SHA256: deteksi rekening ganda tanpa membuka isinya
            $t->string('bank_account_name', 150)->nullable();
            $t->timestamp('bank_changed_at')->nullable();
            $t->text('bpjs_kesehatan_number')->nullable();          // terenkripsi
            $t->text('bpjs_tk_number')->nullable();                 // terenkripsi
            $t->decimal('sdm_base_salary', 18, 2)->default(0);     // gaji menurut SDM: dipakai HANYA utk deteksi selisih vs kontrak
            $t->timestamp('synced_at')->nullable();
            $t->string('sync_hash', 64)->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['employment_status', 'department_id']);
            $t->index('full_name');
        });

        // Komponen gaji (tunjangan/potongan). gl_key = kunci semantik yang diterjemahkan Keuangan (MKP) menjadi akun buku besar.
        Schema::create('pay_components', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name', 100);
            $t->string('type', 12);                 // earning | deduction
            $t->string('category', 24);             // basic | fixed_allowance | variable_allowance | overtime | bonus | thr | reimbursement | deduction | loan
            $t->boolean('taxable')->default(true);
            $t->boolean('bpjs_base')->default(false);   // ikut dasar upah BPJS (gaji pokok + tunjangan tetap)
            $t->boolean('prorated')->default(true);     // dikurangi proporsional bila masuk/keluar di tengah bulan
            $t->string('gl_key', 40);
            $t->boolean('is_system')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(100);
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->text('value')->nullable();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Parameter regulasi bersifat INSERT-ONLY dan berlaku per tanggal: tidak pernah ditimpa, supaya payroll lama bisa dihitung ulang persis.
        Schema::create('regulation_parameters', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->index();
            $t->jsonb('value');
            $t->date('effective_from');
            $t->string('source', 255)->nullable();     // dasar hukum / nomor surat edaran
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['code', 'effective_from']);
        });

        Schema::create('tax_ter_rates', function (Blueprint $t) {
            $t->id();
            $t->char('category', 1);                   // A | B | C  (PMK 168/2023)
            $t->decimal('lower_bound', 18, 2);         // batas bawah (eksklusif)
            $t->decimal('upper_bound', 18, 2)->nullable(); // batas atas (inklusif); null = tak terbatas
            $t->decimal('rate', 6, 4);                 // persen, mis. 1.2500
            $t->date('effective_from');
            $t->timestamps();
            $t->index(['category', 'effective_from', 'lower_bound']);
        });

        // Saldo awal tahun berjalan: dipakai saat PAYBRIDGE mulai dipakai di tengah tahun (mis. Oktober) agar PPh 21 Desember benar.
        Schema::create('opening_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('months_counted')->default(0);
            $t->decimal('gross_ytd', 18, 2)->default(0);        // penghasilan bruto Jan s.d. bulan sebelum go-live
            $t->decimal('pension_ytd', 18, 2)->default(0);      // iuran JHT+JP karyawan (pengurang)
            $t->decimal('pph21_ytd', 18, 2)->default(0);        // PPh 21 yang sudah dipotong
            $t->string('source_note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['employee_id', 'year']);
        });

        // Ringkasan absensi/cuti per bulan yang ditarik dari SDM. Disimpan agar perhitungan payroll bisa dibuktikan ulang.
        Schema::create('attendance_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');
            $t->unsignedSmallInteger('scheduled_days')->default(0);
            $t->decimal('present_days', 6, 1)->default(0);
            $t->unsignedSmallInteger('late_count')->default(0);
            $t->unsignedInteger('late_minutes')->default(0);
            $t->decimal('alpha_days', 6, 1)->default(0);
            $t->decimal('sick_days', 6, 1)->default(0);
            $t->decimal('permit_days', 6, 1)->default(0);
            $t->decimal('paid_leave_days', 6, 1)->default(0);
            $t->decimal('unpaid_leave_days', 6, 1)->default(0);
            $t->decimal('wfh_days', 6, 1)->default(0);
            $t->jsonb('details')->nullable();           // baris harian: tanggal, status, check_in, check_out
            $t->string('source_hash', 64)->nullable();
            $t->timestamp('fetched_at')->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        foreach (['attendance_snapshots', 'opening_balances', 'tax_ter_rates', 'regulation_parameters', 'settings', 'pay_components', 'employees', 'grades', 'departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
