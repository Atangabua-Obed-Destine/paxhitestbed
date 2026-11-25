<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student credit in the ledger (account 419, Clients créditeurs).
 *
 * An overpayment is money the school holds for the student until it is used on
 * another fee or refunded, so it is a debt, not income. Each row here is one
 * ledger entry that moves such money:
 *
 *   hold     Dr fee income / Cr 419   the excess on the fee it was paid on
 *   release  Dr 419 / Cr fee income   credit used on another fee
 *   refund   Dr 419 / Cr cash         credit paid back to the student
 *
 * Rows are never deleted: a posting that is no longer right is reversed
 * (status 'reversed', reversal_entry_id) and a new one written.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('student_credit_postings')) {
            Schema::create('student_credit_postings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('student_credit_id');
                $table->unsignedBigInteger('credit_application_id')->nullable();
                $table->string('kind', 16); // hold | release | refund
                $table->decimal('amount', 15, 2);
                $table->date('entry_date');
                $table->unsignedBigInteger('debit_account_id');
                $table->unsignedBigInteger('credit_account_id');
                $table->unsignedBigInteger('fee_category_id')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status', 16)->default('active'); // active | reversed
                $table->unsignedBigInteger('reversal_entry_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['student_credit_id', 'status']);
                $table->index('credit_application_id');
                $table->index('journal_entry_id');
            });
        }

        $account = DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', '419')->value('id');

        if ($account && !DB::table('accounting_settings')->where('setting_key', 'student_credit_account_id')->exists()) {
            DB::table('accounting_settings')->insert([
                'setting_key' => 'student_credit_account_id',
                'setting_value' => (string) $account,
                'setting_type' => 'integer',
                'category' => 'fees',
                'description' => 'Liability account student credit (overpayments) is held on',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_credit_postings');
        DB::table('accounting_settings')->where('setting_key', 'student_credit_account_id')->delete();
    }
};
