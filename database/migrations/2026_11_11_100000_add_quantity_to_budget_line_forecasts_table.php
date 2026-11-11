<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let any line on the sheet be budgeted as "a quantity at a unit amount".
 *
 * Tuition is already worked out as students × fee. The same reasoning applies
 * to most of the rest of the sheet — twelve months of electricity at 85,000,
 * forty desks at 25,000 — and the Bursar wants the sheet to keep that working
 * rather than only the product.
 *
 * The student count is a whole number; a quantity need not be (2.5 tonnes of
 * cement), so it gets its own column rather than widening student_count.
 * Rows with rate_basis 'unit' use quantity; the student forecasts are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('budget_line_forecasts', 'quantity')) {
            Schema::table('budget_line_forecasts', function (Blueprint $table) {
                $table->decimal('quantity', 15, 2)->nullable()->after('student_count');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('budget_line_forecasts', 'quantity')) {
            Schema::table('budget_line_forecasts', function (Blueprint $table) {
                $table->dropColumn('quantity');
            });
        }
    }
};
