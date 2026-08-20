<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a tuition line say which school it reports.
 *
 * The sheet splits tuition by school, but a fee category cannot tell you which
 * school a payment came from — only the student's programme can. Tagging the
 * line with a faculty lets the actuals resolver walk
 * fee → enrolment → programme → faculty → line, instead of dropping every
 * franc of tuition onto whichever line the fee category happens to name.
 *
 * Null on every other line: only tuition is split this way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('budget_lines', 'faculty_id')) {
            Schema::table('budget_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('faculty_id')->nullable()->after('section')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('budget_lines', 'faculty_id')) {
            Schema::table('budget_lines', function (Blueprint $table) {
                $table->dropColumn('faculty_id');
            });
        }
    }
};
