<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->boolean('is_reconciled')->default(false)->after('line_order');
            $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
        });
    }

    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->dropColumn(['is_reconciled', 'reconciled_at']);
        });
    }
};
