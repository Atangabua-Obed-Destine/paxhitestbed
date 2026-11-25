<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A bank reconciliation is of a ledger account (chart_of_account_id): the
 * bank, cash or mobile money account whose movements are matched to the
 * statement. The table required a payment account (cash box) as well, which a
 * school without cash boxes set up cannot give, so no reconciliation could
 * ever be saved. The payment account becomes optional; the ledger account is
 * what the screens and BankReconciliationService key on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bank_reconciliations')) {
            return;
        }

        DB::statement('ALTER TABLE bank_reconciliations MODIFY payment_account_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bank_reconciliations MODIFY book_balance DECIMAL(15,2) NOT NULL DEFAULT 0');

        $hasIndex = collect(DB::select("SHOW INDEX FROM bank_reconciliations WHERE Key_name = 'bank_reconciliations_account_date_index'"))->isNotEmpty();
        if (!$hasIndex) {
            DB::statement('CREATE INDEX bank_reconciliations_account_date_index ON bank_reconciliations (chart_of_account_id, statement_date)');
        }
    }

    public function down(): void
    {
        // Left nullable: rows saved without a payment account would block it.
    }
};
