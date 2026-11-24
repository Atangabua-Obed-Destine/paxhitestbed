<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash boxes, bank accounts and mobile-money wallets each point at their own
 * ledger account, and each kind of payment method has a default ledger
 * account, so money posts to where it actually went rather than to the one
 * cash account a category mapping names.
 *
 * Every column is empty to begin with: until a school links its accounts,
 * postings go exactly where they went before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_accounts', 'chart_of_account_id')) {
                $table->unsignedBigInteger('chart_of_account_id')->nullable()->index()->after('account_type_id');
            }
        });

        if (!Schema::hasTable('ledger_cash_settings')) {
            Schema::create('ledger_cash_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cash_account_id')->nullable();
                $table->unsignedBigInteger('bank_account_id')->nullable();
                $table->unsignedBigInteger('mobile_money_account_id')->nullable();
                $table->unsignedBigInteger('other_account_id')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_cash_settings');
        Schema::table('payment_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('payment_accounts', 'chart_of_account_id')) {
                $table->dropColumn('chart_of_account_id');
            }
        });
    }
};
