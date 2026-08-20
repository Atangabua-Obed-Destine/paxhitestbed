<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a category answer both questions at once.
 *
 * default_account_mappings already says where a category posts in the ledger
 * (debit / credit account). This adds where it appears on the Income &
 * Expenditure sheet, so the two answers live on the same row and cannot drift
 * apart — a category silently mapped for the ledger but not for the sheet is
 * exactly how money goes missing from a report.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('default_account_mappings', 'budget_line_id')) {
            Schema::table('default_account_mappings', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_line_id')->nullable()->after('category_id')->index();
            });
        }

        // Expense rows record the line directly too, so re-tagging a single
        // transaction never requires changing the rule for its whole category.
        if (!Schema::hasColumn('expenses', 'budget_line_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_line_id')->nullable()->after('category_id')->index();
            });
        }

        if (!Schema::hasColumn('incomes', 'budget_line_id')) {
            Schema::table('incomes', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_line_id')->nullable()->after('category_id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['default_account_mappings', 'expenses', 'incomes'] as $table) {
            if (Schema::hasColumn($table, 'budget_line_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('budget_line_id');
                });
            }
        }
    }
};
