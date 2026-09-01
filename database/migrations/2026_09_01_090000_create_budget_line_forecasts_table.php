<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the reasoning behind a budgeted figure, not just the figure.
 *
 * A tuition line is budgeted by expecting a number of students at a rate. Saving
 * only the product loses the assumption: nobody can tell afterwards whether
 * 21,450,000 was a forecast or a guess, next year's planning starts from a bare
 * number, and nothing can be recalculated when fees change.
 *
 * One row per budget per line. The computed amount is stored alongside the
 * inputs so a later change to fees is visible as a difference rather than
 * silently rewriting a figure somebody has already approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_line_forecasts')) {
            return;
        }

        Schema::create('budget_line_forecasts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('budget_id');
            $table->unsignedBigInteger('budget_line_id');

            $table->unsignedInteger('student_count')->default(0);
            $table->decimal('rate', 15, 2)->default(0);

            // Whether the rate was taken from the configured fees or typed over.
            $table->string('rate_basis', 20)->default('weighted');

            // What the two produced when it was saved. Kept so a later change to
            // the configured fees shows up as a discrepancy to be reviewed
            // rather than a number that moved on its own.
            $table->decimal('computed_amount', 15, 2)->default(0);

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['budget_id', 'budget_line_id']);
            $table->index('budget_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_line_forecasts');
    }
};
