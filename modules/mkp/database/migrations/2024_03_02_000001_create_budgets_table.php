<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month'); // 1-12
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
