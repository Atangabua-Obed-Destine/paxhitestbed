<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let transaction_mappings hold the transaction type payment plans already post.
 *
 * PaymentPlanPaymentObserver posts every instalment payment as
 * 'payment_plan_payment', and LedgerSyncService::TYPES has listed that type all
 * along — but the column is an ENUM naming only fee, income, expense and
 * payroll.
 *
 * What that costs depends on the server's SQL mode, and neither outcome tells
 * anybody:
 *
 *   - outside strict mode MySQL writes an empty string, so the mapping exists
 *     but belongs to no type. The journal entry is there, yet every screen that
 *     looks the payment up by type — the mappings list, the posted check, the
 *     credit audit, a reversal — cannot find it;
 *   - under strict mode the insert throws, autoMap catches it and returns false,
 *     and the payment is recorded with no ledger entry at all.
 *
 * This is the same fault, in the same shape, as the one
 * 2026_08_27_090000_widen_mapping_type_enum fixed on default_account_mappings.
 * The cure is the same: widen the column to what the application already posts,
 * and recover the rows the narrow column blanked.
 */
return new class extends Migration
{
    private const TYPES = ['fee', 'income', 'expense', 'payroll', 'payment_plan_payment'];

    public function up(): void
    {
        if (!Schema::hasTable('transaction_mappings')) {
            return;
        }

        $list = implode(',', array_map(fn ($t) => "'" . $t . "'", self::TYPES));

        DB::statement(
            "ALTER TABLE `transaction_mappings`
             MODIFY `transaction_type` ENUM({$list}) NOT NULL"
        );

        // Recover what the old ENUM blanked. Only payment plan payments were
        // ever posted under a type the column could not hold, so a blank row
        // whose id matches a payment is that payment's mapping.
        if (Schema::hasTable('payment_plan_payments')) {
            DB::table('transaction_mappings')
                ->where('transaction_type', '')
                ->whereIn('transaction_id', DB::table('payment_plan_payments')->select('id'))
                ->update(['transaction_type' => 'payment_plan_payment']);
        }

        // Anything still blank belongs to no type, so nothing can reach it —
        // not the screens, not a reversal, not the audit. Its journal entry is
        // left alone; only the unusable mapping goes.
        DB::table('transaction_mappings')->where('transaction_type', '')->delete();
    }

    public function down(): void
    {
        if (!Schema::hasTable('transaction_mappings')) {
            return;
        }

        // Rows on the new type would be blanked again on the way down, and a
        // mapping belonging to no type is worse than none.
        DB::table('transaction_mappings')->where('transaction_type', 'payment_plan_payment')->delete();

        DB::statement(
            "ALTER TABLE `transaction_mappings`
             MODIFY `transaction_type` ENUM('fee','income','expense','payroll') NOT NULL"
        );
    }
};
