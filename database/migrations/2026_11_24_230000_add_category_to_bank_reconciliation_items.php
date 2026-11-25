<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A bank charge or interest posted from a reconciliation is an expense or an
 * income like any other, so it is filed under a category: the category's
 * mapping gives both its ledger account and its budget line, and the budget
 * sheet and daybook count it (BudgetActualsService, DaybookService).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_reconciliation_items') && !Schema::hasColumn('bank_reconciliation_items', 'category_id')) {
            Schema::table('bank_reconciliation_items', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('item_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_reconciliation_items', 'category_id')) {
            Schema::table('bank_reconciliation_items', fn (Blueprint $table) => $table->dropColumn('category_id'));
        }
    }
};
