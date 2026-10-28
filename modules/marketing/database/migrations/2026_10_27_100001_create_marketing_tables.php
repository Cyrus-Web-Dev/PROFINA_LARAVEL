<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semua uang: numeric(18,2) (BUKAN float). Qty: numeric(14,3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->string('key', 40)->primary();   // mis. QT-202610
            $t->unsignedInteger('last_number')->default(0);
        });

        Schema::create('price_levels', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();     // umum | reseller | grosir ...
            $t->string('name', 60);
            $t->decimal('discount_pct', 5, 2)->default(0); // dipakai bila produk tidak punya harga khusus untuk level ini
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name', 150);
            $t->string('company', 150)->nullable();
            $t->foreignId('price_level_id')->constrained('price_levels')->restrictOnDelete();
            $t->string('email', 150)->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('address')->nullable();
            $t->string('city', 80)->nullable();
            $t->string('npwp', 30)->nullable();
            $t->unsignedSmallInteger('payment_terms_days')->default(0);
            $t->decimal('credit_limit', 18, 2)->default(0);
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // sales penanggung jawab
            $t->string('status', 12)->default('active')->index(); // prospect | active | inactive
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 40)->unique();
            $t->string('name', 180);
            $t->string('unit', 20)->default('pcs');
            $t->text('description')->nullable();
            $t->decimal('base_price', 18, 2)->default(0);       // harga jual dasar (level umum)
            $t->decimal('cost_price', 18, 2)->nullable();       // HPP - HANYA tampil bagi pemegang izin cost.view
            $t->decimal('min_order_qty', 14, 3)->default(1);    // MOQ dasar
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('product_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('price_level_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 18, 2);
            $t->decimal('min_qty', 14, 3)->nullable();           // MOQ khusus level ini
            $t->unique(['product_id', 'price_level_id']);
        });

        Schema::create('customer_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 18, 2);
            $t->decimal('min_qty', 14, 3)->nullable();           // MOQ khusus pelanggan ini
            $t->unique(['customer_id', 'product_id']);
        });

        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('draft')->index(); // draft | sent | approved | rejected | expired
            $t->date('quote_date');
            $t->date('valid_until');
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('tax_pct', 5, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->text('terms')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('reject_reason', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description', 255);
            $t->string('unit', 20)->default('pcs');
            $t->decimal('qty', 14, 3);
            $t->decimal('unit_price', 18, 2);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->decimal('line_total', 18, 2);
            $t->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('sales_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete(); // 1 penawaran -> 1 SO
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('confirmed')->index(); // confirmed | in_production | ready | partial | delivered | cancelled
            $t->date('order_date');
            $t->date('target_date')->nullable();
            $t->decimal('subtotal', 18, 2)->default(0);
            $t->decimal('tax_pct', 5, 2)->default(0);
            $t->decimal('tax_amount', 18, 2)->default(0);
            $t->decimal('total', 18, 2)->default(0);
            $t->text('ship_to')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('sales_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description', 255);
            $t->string('unit', 20)->default('pcs');
            $t->decimal('qty', 14, 3);
            $t->decimal('delivered_qty', 14, 3)->default(0);
            $t->decimal('unit_price', 18, 2);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->decimal('line_total', 18, 2);
            $t->unsignedSmallInteger('sort')->default(0);
        });

        Schema::create('delivery_notes', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('status', 12)->default('draft')->index(); // draft | shipped | received
            $t->date('delivery_date');
            $t->string('driver', 100)->nullable();
            $t->string('vehicle_no', 20)->nullable();
            $t->text('ship_to')->nullable();
            $t->string('received_by', 100)->nullable();
            $t->timestamp('received_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('delivery_note_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sales_order_item_id')->constrained()->restrictOnDelete();
            $t->decimal('qty', 14, 3);
        });

        Schema::create('complaints', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('category', 20)->default('kualitas'); // kualitas | pengiriman | harga | layanan | lainnya
            $t->string('priority', 10)->default('normal');   // low | normal | high
            $t->string('subject', 180);
            $t->text('description');
            $t->string('status', 14)->default('open')->index(); // open | in_progress | resolved | closed
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolution')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['complaints', 'delivery_note_items', 'delivery_notes', 'sales_order_items', 'sales_orders', 'quotation_items', 'quotations', 'customer_prices', 'product_prices', 'products', 'customers', 'price_levels', 'number_sequences'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
