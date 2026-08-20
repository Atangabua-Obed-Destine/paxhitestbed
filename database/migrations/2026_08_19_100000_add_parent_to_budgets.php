<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a departmental or project budget say which annual budget it sits under.
 *
 * Without it the two are only implicitly related — same dates, same fiscal year —
 * which is not enough to answer the question that matters: of the 2,000,000
 * budgeted for stationery on the annual sheet, how much has been delegated to
 * departments and how much is still held centrally.
 *
 * Null for an annual budget, which is the top of its own tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('budgets', 'parent_id')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('is_institutional')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('budgets', 'parent_id')) {
            Schema::table('budgets', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
    }
};
