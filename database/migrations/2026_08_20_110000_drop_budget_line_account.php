<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the budget_line_account pivot — a second bridge that was never used.
 *
 * Budget lines and the chart of accounts are two views of the same money: the
 * chart is organised by nature of expense for the OHADA statements, the budget
 * sheet by activity for the Bursar. They are joined through
 * `default_account_mappings`, where every category carries both a chart account
 * and a budget line.
 *
 * This pivot was a second, parallel way to express the same link. It was never
 * populated (0 rows, 0 of 66 lines) and nothing ever read it, so having it
 * around made the design look like it kept two competing structures.
 *
 * Refuses to run if anyone did populate it, rather than discarding their work.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budget_line_account')) {
            return;
        }

        $rows = DB::table('budget_line_account')->count();
        if ($rows > 0) {
            throw new RuntimeException(
                "budget_line_account holds {$rows} row(s). It was expected to be empty. "
                . 'Move those links into default_account_mappings before dropping the table.'
            );
        }

        Schema::drop('budget_line_account');
    }

    public function down(): void
    {
        if (Schema::hasTable('budget_line_account')) {
            return;
        }

        Schema::create('budget_line_account', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('budget_line_id')->index();
            $table->unsignedBigInteger('chart_of_account_id')->index();
            $table->timestamps();

            $table->unique(['budget_line_id', 'chart_of_account_id'], 'budget_line_account_unique');
        });
    }
};
