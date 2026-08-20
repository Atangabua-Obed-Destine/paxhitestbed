<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the ledger record a posting that no person made.
 *
 * TransactionAutoMapService fell back to user id 1 whenever there was no
 * authenticated user — console commands, queued jobs, the scheduler, seeders.
 * Where user 1 does not exist the foreign key rejects the insert, so the
 * posting fails and the transaction is recorded without ever reaching the
 * ledger. A system posting genuinely has no user, so the columns become
 * nullable and the service stops inventing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_mappings')) {
            DB::statement('ALTER TABLE `transaction_mappings` MODIFY `mapped_by` BIGINT UNSIGNED NULL');
        }

        foreach (['created_by', 'posted_by'] as $column) {
            if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', $column)) {
                DB::statement("ALTER TABLE `journal_entries` MODIFY `{$column}` BIGINT UNSIGNED NULL");
            }
        }
    }

    public function down(): void
    {
        // Not reversed: restoring NOT NULL would fail wherever a system posting
        // exists, and those are legitimate rows.
    }
};
