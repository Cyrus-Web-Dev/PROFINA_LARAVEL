<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->string('run_number', 30)->unique();
            $t->string('type', 12)->default('regular');                // regular | thr | bonus | final | correction
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('pay_date');
            $t->string('title', 150)->nullable();
            // draft -> calculated -> in_review -> verified -> approved(terkunci) -> funding -> funded -> disbursing -> paid -> closed ; rejected | cancelled
            $t->string('status', 14)->default('draft')->index();
            $t->date('thr_holiday_date')->nullable();

            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('calculated_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('verification_note')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('second_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('second_approved_at')->nullable();
            $t->boolean('requires_dual_approval')->default(false);
            $t->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable();
            $t->text('rejection_note')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();

            $t->timestamp('locked_at')->nullable();                     // terisi saat disetujui: isi putaran menjadi tidak bisa diubah
            $t->string('snapshot_hash', 64)->nullable();                // SHA-256 atas seluruh item saat dikunci

            $t->unsignedInteger('employee_count')->default(0);
            $t->decimal('total_gross', 18, 2)->default(0);
            $t->decimal('total_employer_bpjs', 18, 2)->default(0);
            $t->decimal('total_employee_bpjs', 18, 2)->default(0);
            $t->decimal('total_pph21', 18, 2)->default(0);
            $t->decimal('total_other_deductions', 18, 2)->default(0);
            $t->decimal('total_net', 18, 2)->default(0);
            $t->decimal('total_disbursable', 18, 2)->default(0);        // gaji bersih tanpa item yang ditahan
            $t->decimal('total_employer_cost', 18, 2)->default(0);
            $t->jsonb('totals')->nullable();                            // rincian per kunci buku besar, per departemen
            $t->jsonb('budget_check')->nullable();
            $t->jsonb('calc_meta')->nullable();                         // parameter regulasi yang dipakai, waktu tarik SDM, dst.
            $t->text('notes')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();

            $t->index(['period_year', 'period_month']);
        });

        Schema::create('payroll_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $t->foreignId('contract_id')->nullable()->constrained('compensation_contracts')->nullOnDelete();
            $t->string('contract_terms_hash', 64)->nullable();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('grade_code', 20)->nullable();
            $t->jsonb('employee_snapshot');                              // nip, nama, jabatan, PTKP, bank (dimasker) saat dihitung
            $t->string('bank_account_hash', 64)->nullable();

            $t->unsignedSmallInteger('days_scheduled')->default(0);
            $t->decimal('days_present', 6, 1)->default(0);
            $t->decimal('days_absent', 6, 1)->default(0);
            $t->decimal('days_unpaid_leave', 6, 1)->default(0);
            $t->decimal('proration_factor', 8, 6)->default(1);
            $t->decimal('overtime_hours', 6, 2)->default(0);

            $t->jsonb('lines');                                          // seluruh baris komponen: [{code,name,type,category,amount,taxable}]
            $t->decimal('basic_salary', 18, 2)->default(0);
            $t->decimal('fixed_allowances', 18, 2)->default(0);
            $t->decimal('variable_earnings', 18, 2)->default(0);
            $t->decimal('gross_earnings', 18, 2)->default(0);

            $t->decimal('bpjs_kes_employee', 18, 2)->default(0);
            $t->decimal('bpjs_kes_company', 18, 2)->default(0);
            $t->decimal('bpjs_jht_employee', 18, 2)->default(0);
            $t->decimal('bpjs_jht_company', 18, 2)->default(0);
            $t->decimal('bpjs_jkk_company', 18, 2)->default(0);
            $t->decimal('bpjs_jkm_company', 18, 2)->default(0);
            $t->decimal('bpjs_jp_employee', 18, 2)->default(0);
            $t->decimal('bpjs_jp_company', 18, 2)->default(0);

            $t->decimal('tax_gross', 18, 2)->default(0);                 // dasar pengenaan PPh 21 bulan ini
            $t->decimal('pph21', 18, 2)->default(0);                     // boleh negatif pada masa pajak terakhir (lebih potong dikembalikan)
            $t->string('pph21_method', 12)->default('none');             // ter | final | exempt | none
            $t->string('ter_category', 1)->nullable();
            $t->decimal('ter_rate', 6, 4)->nullable();
            $t->jsonb('tax_detail')->nullable();

            $t->decimal('loan_deduction', 18, 2)->default(0);
            $t->decimal('other_deductions', 18, 2)->default(0);
            $t->decimal('total_employee_deductions', 18, 2)->default(0);
            $t->decimal('net_pay', 18, 2)->default(0);
            $t->decimal('employer_cost', 18, 2)->default(0);

            $t->boolean('is_held')->default(false);
            $t->text('held_reason')->nullable();
            $t->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('held_at')->nullable();

            $t->string('item_hash', 64);                                  // SHA-256 isi item: deteksi perubahan setelah dihitung
            $t->string('status', 12)->default('calculated');              // calculated | paid | failed
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_ref', 80)->nullable();
            $t->string('payment_note', 255)->nullable();
            $t->timestamp('payslip_published_at')->nullable();
            $t->timestamp('payslip_emailed_at')->nullable();
            $t->string('payslip_email_status', 255)->nullable();
            $t->timestamps();

            $t->unique(['payroll_run_id', 'employee_id']);
            $t->index('employee_id');
        });

        // Input variabel (lembur, bonus, tunjangan insidentil, koreksi, potongan). Wajib disetujui orang lain sebelum dipakai.
        Schema::create('payroll_inputs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $t->foreignId('pay_component_id')->nullable()->constrained('pay_components')->nullOnDelete();
            $t->string('type', 16);                       // overtime | bonus | allowance | reimbursement | deduction | correction
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->date('work_date')->nullable();            // khusus lembur
            $t->string('day_type', 10)->nullable();       // weekday | restday | holiday
            $t->decimal('hours', 5, 2)->nullable();
            $t->decimal('amount', 18, 2)->default(0);      // lembur: DIHITUNG sistem saat putaran; tipe lain: diisi pengaju
            $t->string('description', 255);
            $t->string('evidence_path', 255)->nullable();
            $t->jsonb('attendance_check')->nullable();     // hasil pencocokan dengan absensi SDM
            $t->string('status', 12)->default('pending')->index(); // pending | approved | rejected | applied | cancelled
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('approval_note')->nullable();
            $t->foreignId('applied_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
            $t->timestamps();
            $t->index(['employee_id', 'period_year', 'period_month']);
        });

        Schema::create('employee_loans', function (Blueprint $t) {
            $t->id();
            $t->string('loan_number', 30)->unique();
            $t->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $t->decimal('principal', 18, 2);
            $t->unsignedSmallInteger('installments');
            $t->decimal('installment_amount', 18, 2);
            $t->unsignedSmallInteger('paid_installments')->default(0);
            $t->decimal('remaining', 18, 2);
            $t->unsignedSmallInteger('start_year');
            $t->unsignedTinyInteger('start_month');
            $t->string('reason', 255)->nullable();
            $t->string('status', 12)->default('pending')->index(); // pending | active | settled | rejected | cancelled
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('loan_repayments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('loan_id')->constrained('employee_loans')->cascadeOnDelete();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $t->decimal('amount', 18, 2);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['loan_id', 'payroll_run_id']);
        });

        Schema::create('payroll_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('code', 40)->index();
            $t->string('severity', 10);                  // info | warning | critical
            $t->boolean('is_blocking')->default(false);
            $t->string('title', 200);
            $t->text('detail')->nullable();
            $t->jsonb('context')->nullable();
            $t->string('status', 12)->default('open')->index(); // open | resolved | waived
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_note')->nullable();
            $t->string('fingerprint', 64);
            $t->timestamps();
            $t->unique(['payroll_run_id', 'fingerprint']);
        });

        Schema::create('disbursement_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $t->string('batch_number', 30)->unique();
            $t->string('bank_code', 12);                  // ALL | BCA | MANDIRI | BNI | BRI | LAINNYA
            $t->unsignedInteger('item_count');
            $t->decimal('total_amount', 18, 2);
            $t->string('file_path', 255);
            $t->string('file_sha256', 64);
            $t->string('status', 14)->default('generated'); // generated | reconciled | mismatch
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('generated_at')->nullable();
            $t->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reconciled_at')->nullable();
            $t->jsonb('reconciliation')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['disbursement_batches', 'payroll_findings', 'loan_repayments', 'employee_loans', 'payroll_inputs', 'payroll_items', 'payroll_runs'] as $t) {
            Schema::dropIfExists($t);
        }
    }

    // Satu putaran reguler aktif per bulan (partial unique index PostgreSQL) ditambahkan di migrasi audit_ledger.
};
