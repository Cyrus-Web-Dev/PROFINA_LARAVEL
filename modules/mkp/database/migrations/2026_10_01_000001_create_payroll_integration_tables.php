<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jembatan Keuangan <-> PAYBRIDGE. Pola sama dengan procurement_fundings (PROCURA). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_fundings', function (Blueprint $t) {
            $t->id();
            $t->string('payroll_number', 40)->unique();            // PFD-PR-2610-001
            $t->string('run_number', 30);
            $t->string('run_type', 12)->default('regular');
            $t->string('title', 200)->nullable();
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->date('pay_date')->nullable();
            // submitted -> approved -> disbursed ; rejected | revision | cancelled
            $t->string('status', 12)->default('submitted')->index();
            $t->unsignedInteger('employee_count')->default(0);
            $t->decimal('amount_net_total', 18, 2)->default(0);     // total utang gaji (diakru saat disetujui)
            $t->decimal('amount_disbursable', 18, 2)->default(0);   // total transfer; TIDAK bisa diubah Keuangan
            $t->decimal('approved_amount', 18, 2)->nullable();
            $t->json('breakdown');                                  // {debit:{kunci:nominal}, credit:{kunci:nominal}}
            $t->json('bank_batches')->nullable();                   // [{batch_number,item_count,total_amount,file_sha256}]
            $t->string('snapshot_hash', 64)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('decided_by_name', 120)->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->string('finance_ref', 40)->nullable();
            $t->foreignId('accrual_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $t->decimal('disbursed_amount', 18, 2)->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->string('disbursement_method', 20)->nullable();
            $t->string('disbursement_ref', 80)->nullable();
            $t->foreignId('cash_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->foreignId('disbursement_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $t->json('snapshot')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });

        Schema::create('payroll_remittances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('remote_id')->unique();          // id setoran di PAYBRIDGE
            $t->string('type', 10);                                // pph21 | bpjs_kes | bpjs_tk
            $t->string('run_number', 30);
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->decimal('amount', 18, 2);
            $t->date('due_date');
            $t->date('report_due_date')->nullable();
            $t->string('status', 10)->default('pending')->index(); // pending | paid
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_ref', 80)->nullable();
            $t->foreignId('cash_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('payroll_events', function (Blueprint $t) {
            $t->id();
            $t->string('delivery_id', 80)->nullable()->unique();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('received');
            $t->text('note')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payroll_events', 'payroll_remittances', 'payroll_fundings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
