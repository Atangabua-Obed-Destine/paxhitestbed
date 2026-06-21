<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The YearEndClosing model references columns that the create migration never added
 * (closing_reference, closing_journal_entry_id, checklist, started_at, completed_at,
 * approved_by, approved_at), so the feature errored on save. Add them so the model and
 * table align and year-end closing can actually run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('year_end_closings', function (Blueprint $table) {
            if (!Schema::hasColumn('year_end_closings', 'closing_reference')) {
                $table->string('closing_reference')->nullable()->after('closing_date');
            }
            if (!Schema::hasColumn('year_end_closings', 'closing_journal_entry_id')) {
                $table->unsignedBigInteger('closing_journal_entry_id')->nullable();
            }
            if (!Schema::hasColumn('year_end_closings', 'checklist')) {
                $table->json('checklist')->nullable();
            }
            if (!Schema::hasColumn('year_end_closings', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('year_end_closings', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (!Schema::hasColumn('year_end_closings', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('year_end_closings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('year_end_closings', function (Blueprint $table) {
            foreach (['closing_reference', 'closing_journal_entry_id', 'checklist', 'started_at', 'completed_at', 'approved_by', 'approved_at'] as $col) {
                if (Schema::hasColumn('year_end_closings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
