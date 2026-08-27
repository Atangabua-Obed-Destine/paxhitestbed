<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a budget line belong to a trading activity.
 *
 * The Bursar's daybook carries a profit-centre block: each trading activity —
 * canteen, uniforms, books, the phone booth — appears twice, once for what it
 * earned and once for what it cost, so the margin per activity falls out. The
 * annual sheet cannot answer "is the canteen making money" at all.
 *
 * Modelled as a name on the line rather than a table of its own. Lines sharing
 * a name are one activity, and each line's existing `section` decides which
 * side it lands on, so a canteen income line and a canteen expenditure line
 * pair themselves. A school with seventeen activities configures them exactly
 * as an institution with two does — which is what a generic module has to mean.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('budget_lines') || Schema::hasColumn('budget_lines', 'profit_centre')) {
            return;
        }

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('profit_centre')->nullable()->after('faculty_id');
            $table->index('profit_centre');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('budget_lines') || !Schema::hasColumn('budget_lines', 'profit_centre')) {
            return;
        }

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['profit_centre']);
            $table->dropColumn('profit_centre');
        });
    }
};
