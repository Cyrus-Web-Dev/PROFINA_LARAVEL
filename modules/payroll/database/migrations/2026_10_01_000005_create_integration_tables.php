<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sistem lain (SDM, Keuangan, ...) yang boleh memanggil REST API PAYBRIDGE. Pola yang sama dengan PROCURA.
        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('system', 20)->index();                         // sdm | keuangan | audit | lainnya
            $t->string('token_prefix', 12)->index();
            $t->string('token_hash', 64)->unique();                    // SHA-256; token asli hanya tampil sekali
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

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('pending')->index();    // pending | delivered | failed
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('next_retry_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $t->string('key', 100);
            $t->string('resource_type', 40)->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['api_client_id', 'key']);
        });

        // Webhook MASUK (SDM -> PAYBRIDGE): disimpan dulu, diproses idempoten, bisa diputar ulang.
        Schema::create('inbound_events', function (Blueprint $t) {
            $t->id();
            $t->string('source', 20);
            $t->string('delivery_id', 80)->nullable();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('received');            // received | processed | failed | ignored
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['source', 'delivery_id']);
        });
    }

    public function down(): void
    {
        foreach (['inbound_events', 'idempotency_keys', 'webhook_deliveries', 'api_clients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
