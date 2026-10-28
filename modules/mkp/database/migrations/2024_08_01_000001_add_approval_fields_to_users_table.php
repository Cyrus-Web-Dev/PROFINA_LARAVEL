<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // pending = menunggu persetujuan | active | rejected = ditolak | inactive = dinonaktifkan
            // Default 'active' supaya akun yang sudah ada sebelum fitur ini tetap bisa login.
            $table->string('status', 20)->default('active')->index();
            $table->string('position', 50)->nullable();          // posisi kerja: accounting/finance/pajak/payroll/lainnya
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('status_note')->nullable();             // alasan penolakan / penonaktifan
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'position', 'approved_by', 'approved_at', 'status_note']);
        });
    }
};
