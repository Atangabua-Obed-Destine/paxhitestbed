<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RecurringJournalEntryLine uses soft deletes, but its table was created
 * without the column, so the Recurring Entries screen failed on every load as
 * soon as one entry existed ("Unknown column ... deleted_at").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recurring_journal_entry_lines') && !Schema::hasColumn('recurring_journal_entry_lines', 'deleted_at')) {
            Schema::table('recurring_journal_entry_lines', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recurring_journal_entry_lines', 'deleted_at')) {
            Schema::table('recurring_journal_entry_lines', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
