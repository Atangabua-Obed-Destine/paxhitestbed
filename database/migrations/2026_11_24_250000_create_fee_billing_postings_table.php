<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fees on accrual: what a student owes is in the books.
 *
 * Assigning a fee posts Dr 411 (Scolarités à recevoir) / Cr the fee's income
 * account, on its assign date; payments then settle 411 instead of crediting
 * income. Each row here is one billing entry (FeeAccrual); a billing that is
 * no longer right is reversed and written again, never deleted.
 *
 * Accrual starts switched OFF. `php artisan fees:accrual --enable` switches it
 * on and brings every existing fee into line in one go: a half-switched ledger
 * (payments settling 411 with no bills behind them) would be wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_billing_postings')) {
            Schema::create('fee_billing_postings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fee_id');
                $table->decimal('amount', 15, 2);
                $table->date('entry_date');
                $table->unsignedBigInteger('debit_account_id');
                $table->unsignedBigInteger('credit_account_id');
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status', 16)->default('active');
                $table->unsignedBigInteger('reversal_entry_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['fee_id', 'status']);
            });
        }

        $receivable = DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', '411')->value('id');
        $settings = [
            ['fee_receivable_account_id', (string) ($receivable ?? ''), 'integer', 'Receivable account fees are billed to (accrual)'],
            ['fee_accrual_enabled', '0', 'boolean', 'Fees on accrual: switched on by php artisan fees:accrual --enable'],
        ];

        foreach ($settings as [$key, $value, $type, $description]) {
            if (!DB::table('accounting_settings')->where('setting_key', $key)->exists()) {
                DB::table('accounting_settings')->insert([
                    'setting_key' => $key, 'setting_value' => $value, 'setting_type' => $type,
                    'category' => 'fees', 'description' => $description,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_billing_postings');
        DB::table('accounting_settings')->whereIn('setting_key', ['fee_receivable_account_id', 'fee_accrual_enabled'])->delete();
    }
};
