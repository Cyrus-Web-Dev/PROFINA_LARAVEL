<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengajuan dana gaji ke Keuangan (MKP). Polanya sama dengan pengajuan dana PROCURA -> MKP.
        Schema::create('funding_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();
            // submitted -> approved -> disbursed -> confirmed ; rejected | revision | cancelled
            $t->string('status', 12)->default('submitted')->index();
            $t->jsonb('breakdown');                       // kunci buku besar semantik -> nominal (lihat FundingService)
            $t->decimal('amount_net_total', 18, 2);        // total utang gaji (semua item)
            $t->decimal('amount_disbursable', 18, 2);      // yang boleh ditransfer (tanpa item ditahan)
            $t->decimal('amount_approved', 18, 2)->nullable();
            $t->string('finance_ref', 80)->nullable();
            $t->string('decided_by_name', 120)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->decimal('disbursed_amount', 18, 2)->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->string('disbursed_by_name', 120)->nullable();
            $t->string('disbursement_ref', 80)->nullable();
            $t->jsonb('journal_refs')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        // Kewajiban setoran PPh 21 & BPJS (jatuh tempo + bukti bayar).
        Schema::create('remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $t->string('type', 10);                       // pph21 | bpjs_kes | bpjs_tk
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->decimal('amount', 18, 2);
            $t->date('due_date');
            $t->date('report_due_date')->nullable();
            $t->string('status', 10)->default('pending')->index(); // pending | paid
            $t->timestamp('paid_at')->nullable();
            $t->string('paid_by_name', 120)->nullable();
            $t->string('payment_ref', 80)->nullable();    // NTPN / nomor bukti bayar BPJS
            $t->string('finance_ref', 80)->nullable();
            $t->string('journal_ref', 60)->nullable();
            $t->timestamp('reminder_sent_at')->nullable();
            $t->timestamps();
            $t->unique(['payroll_run_id', 'type']);
        });

        // Kotak masuk/keluar kabar antar-sistem (Keuangan <-> Payroll).
        Schema::create('integration_messages', function (Blueprint $t) {
            $t->id();
            $t->string('direction', 3);                   // in | out
            $t->string('system', 20);                     // keuangan | sdm
            $t->foreignId('funding_request_id')->nullable()->constrained('funding_requests')->nullOnDelete();
            $t->string('subject', 200);
            $t->text('body')->nullable();
            $t->string('author', 120)->nullable();
            $t->string('delivery_id', 80)->nullable()->unique();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        Schema::create('budget_plans', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('fiscal_year');
            $t->unsignedSmallInteger('version')->default(1);
            $t->string('name', 150);
            // draft -> submitted -> approved -> active ; superseded | rejected
            $t->string('status', 12)->default('draft')->index();
            $t->decimal('total_amount', 18, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->text('rejection_note')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->timestamp('pushed_to_finance_at')->nullable();
            $t->timestamps();
            $t->unique(['fiscal_year', 'version']);
        });

        Schema::create('budget_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('budget_plan_id')->constrained('budget_plans')->cascadeOnDelete();
            $t->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $t->unsignedTinyInteger('month');
            // basic | allowance | overtime | bonus | thr | bpjs_company | other
            $t->string('category', 16);
            $t->unsignedInteger('planned_headcount')->nullable();
            $t->decimal('amount', 18, 2)->default(0);
            $t->timestamps();
            $t->unique(['budget_plan_id', 'department_id', 'month', 'category']);
        });
    }

    public function down(): void
    {
        foreach (['budget_lines', 'budget_plans', 'integration_messages', 'remittances', 'funding_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
