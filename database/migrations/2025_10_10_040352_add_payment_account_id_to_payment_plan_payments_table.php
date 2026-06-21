<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_account_id')->nullable()->after('note');
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->onDelete('set null');
            $table->index('payment_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->dropForeign(['payment_account_id']);
            $table->dropIndex(['payment_account_id']);
            $table->dropColumn('payment_account_id');
        });
    }
};
