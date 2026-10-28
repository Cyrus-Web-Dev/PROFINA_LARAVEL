<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // Menandai akun sebagai akun Kas/Bank agar bisa dipilih di modul Kas & Bank,
            // pembayaran invoice/bill, dan laporan arus kas nanti.
            $table->boolean('is_cash_bank')->default(false)->after('is_header');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn('is_cash_bank');
        });
    }
};
