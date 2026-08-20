<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The coded budget lines behind the Income & Expenditure sheet.
 *
 * These are deliberately NOT chart-of-accounts rows. The diocesan sheet numbers
 * expenditure in the 400s and income in the 600s, whereas OHADA reserves class 6
 * for charges and class 7 for revenue — forcing one scheme into the other would
 * corrupt the statutory chart. A budget line is a management reporting
 * dimension that maps onto one or more OHADA accounts, so the sheet can be
 * finer grained than the legal chart without either distorting the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budget_lines')) {
            Schema::create('budget_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 20);
                $table->string('name');
                $table->string('name_fr')->nullable();
                $table->text('description')->nullable();

                // Which part of the sheet the line belongs to. Capital sits
                // below the closing balance and is never part of expenditure.
                $table->enum('section', ['income', 'expenditure', 'capital'])->index();

                // Group headers (400 ADMINISTRATION) carry no figure of their
                // own; they total their children.
                $table->boolean('is_header')->default(false);
                $table->unsignedBigInteger('parent_id')->nullable()->index();

                // Lines added for this institution that are not on the diocesan
                // template, so an extension stays visibly an extension.
                $table->boolean('is_local')->default(false);

                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique('code');
            });
        }

        // A budget_line_account pivot used to be created here to link lines to
        // statutory accounts. It was never populated and never read: categories
        // already resolve to both a chart account and a budget line through
        // default_account_mappings, so that is the one bridge. Dropped in
        // 2026_08_20_110000_drop_budget_line_account.
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_account');
        Schema::dropIfExists('budget_lines');
    }
};
