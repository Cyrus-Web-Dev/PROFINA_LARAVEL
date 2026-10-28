<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jembatan SDM <-> PAYBRIDGE (Payroll & Budgeting). Pola sama dengan integrasi PROCURA <-> MKP. */
return new class extends Migration
{
    public function up(): void
    {
        // Sistem yang boleh memanggil REST API SDM (saat ini: PAYBRIDGE). Token disimpan sebagai hash SHA-256.
        Schema::create('integration_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('system', 20)->default('payroll')->index();
            $t->string('token_prefix', 12)->index();
            $t->string('token_hash', 64)->unique();
            $t->json('scopes');
            $t->string('webhook_url', 500)->nullable();
            $t->string('webhook_secret', 80)->nullable();
            $t->json('webhook_events')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_used_at')->nullable();
            $t->string('last_used_ip', 45)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('integration_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('integration_client_id')->constrained('integration_clients')->cascadeOnDelete();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('pending')->index();   // pending | delivered | failed
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('next_retry_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        // Webhook MASUK dari PAYBRIDGE (idempoten lewat delivery_id).
        Schema::create('integration_inbox', function (Blueprint $t) {
            $t->id();
            $t->string('source', 20)->default('payroll');
            $t->string('delivery_id', 80)->nullable();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('received');
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['source', 'delivery_id']);
        });

        // Ringkasan slip gaji yang diterbitkan PAYBRIDGE (tanpa rincian pajak/komponen). PDF diunduh dari PAYBRIDGE.
        Schema::create('payslip_mirrors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $t->string('nip', 30)->index();
            $t->string('run_number', 30);
            $t->string('run_type', 12)->default('regular');
            $t->unsignedSmallInteger('period_year');
            $t->unsignedTinyInteger('period_month');
            $t->decimal('gross', 18, 2)->default(0);
            $t->decimal('net', 18, 2)->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['run_number', 'nip']);
        });
    }

    public function down(): void
    {
        foreach (['payslip_mirrors', 'integration_inbox', 'integration_deliveries', 'integration_clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
