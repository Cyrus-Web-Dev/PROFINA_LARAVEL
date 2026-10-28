<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cermin pengajuan dana dari sistem Pengadaan (PROCURA) + keputusan/pencairan/LPJ sisi Keuangan.
        Schema::create('procurement_fundings', function (Blueprint $t) {
            $t->id();
            $t->string('procura_number', 30)->unique();          // FR-2610-0001
            $t->string('request_number', 30)->nullable()->index(); // PR-2610-0001
            $t->string('request_title')->nullable();
            $t->string('category', 30)->nullable();
            $t->decimal('amount', 18, 2);                        // nominal diajukan
            $t->text('purpose')->nullable();
            $t->string('status', 20)->default('submitted')->index(); // submitted|approved|revision|rejected|disbursed|settled|closed|cancelled
            $t->timestamp('submitted_at')->nullable();

            // Keputusan Keuangan
            $t->string('finance_ref', 30)->nullable()->unique();     // nomor dokumen Keuangan (KDP-2610-0001)
            $t->decimal('approved_amount', 18, 2)->nullable();
            $t->string('decided_by_name', 120)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();

            // Pencairan (kas keluar ke Pengadaan)
            $t->decimal('disbursed_amount', 18, 2)->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->string('disbursement_method', 20)->nullable();
            $t->string('disbursement_ref', 60)->nullable();
            $t->foreignId('cash_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->foreignId('disbursement_journal_id')->nullable()->constrained('journals')->nullOnDelete();

            // LPJ dari Pengadaan + jurnalnya
            $t->decimal('actual_spent', 18, 2)->nullable();
            $t->decimal('returned_amount', 18, 2)->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->foreignId('settlement_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $t->foreignId('settlement_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $t->timestamp('settlement_booked_at')->nullable();

            $t->json('snapshot')->nullable();                    // tanggapan terakhir API
            $t->timestamp('synced_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });

        // Buku laporan Pengadaan <-> Keuangan.
        Schema::create('procurement_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('funding_id')->nullable()->constrained('procurement_fundings')->cascadeOnDelete();
            $t->string('direction', 8);                          // masuk (dari Pengadaan) | keluar (dari Keuangan)
            $t->string('kind', 30)->index();                     // pengajuan | keputusan | pencairan | lpj | lpj_dijurnal | catatan
            $t->string('subject', 200);
            $t->text('body')->nullable();
            $t->string('dedupe_key', 80)->nullable();            // anti-duplikat webhook vs sinkron
            $t->string('delivery_id', 40)->nullable()->unique();  // X-Procura-Delivery
            $t->timestamp('read_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['funding_id', 'created_at']);
            $t->index(['direction', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_reports');
        Schema::dropIfExists('procurement_fundings');
    }
};
