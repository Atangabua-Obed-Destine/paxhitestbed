<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a budget hold income as well as expenditure.
 *
 * budget_allocations could only ever reference an expense category, which is
 * why the Income & Expenditure sheet could not be budgeted at all: there was no
 * column an income line could go in. expense_category_id stays for the existing
 * departmental budgets, so both uses coexist.
 *
 * budgets gains an opening balance because the sheet is bracketed by one, and
 * the closing balance of one year becomes the opening balance of the next.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('budget_allocations', 'budget_line_id')) {
            Schema::table('budget_allocations', function (Blueprint $table) {
                $table->unsignedBigInteger('budget_line_id')->nullable()->after('expense_category_id')->index();
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            if (!Schema::hasColumn('budgets', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0)->after('total_amount');
            }
            // Marks a budget as the institutional sheet rather than a
            // departmental pot, so the two are never confused in a list.
            if (!Schema::hasColumn('budgets', 'is_institutional')) {
                $table->boolean('is_institutional')->default(false)->after('type')->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('budget_allocations', 'budget_line_id')) {
            Schema::table('budget_allocations', function (Blueprint $table) {
                $table->dropColumn('budget_line_id');
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            foreach (['opening_balance', 'is_institutional'] as $column) {
                if (Schema::hasColumn('budgets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
