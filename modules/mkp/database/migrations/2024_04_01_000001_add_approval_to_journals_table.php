<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // not_required: di bawah threshold, bisa langsung diposting.
            // pending: di atas threshold, menunggu persetujuan admin sebelum bisa diposting.
            // approved / rejected: hasil keputusan approver.
            $table->enum('approval_status', ['not_required', 'pending', 'approved', 'rejected'])->default('not_required')->after('status');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropColumn(['approval_status', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
