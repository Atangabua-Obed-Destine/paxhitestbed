<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a journal entry admit that no fiscal year covers its date.
 *
 * The column was NOT NULL, so every posting path had to name SOME year — and
 * when none contained the entry's date they all named "whichever year is
 * active". On this database that produced 912 of 924 entries stamped with a
 * fiscal year running October 2026 to July 2027 while being dated October 2025
 * to October 2026, none of them with an accounting period.
 *
 * The damage is that it is invisible. The entries exist, the reports run, and a
 * year filtered by fiscal_year_id silently returns two calendar years of
 * transactions.
 *
 * Null is the honest answer to "which year does 3 March 2024 belong to" when no
 * year covers it, and an honest null is worth far more than a tidy wrong
 * number: accounting:fiscal-year-doctor lists these, and the fix is to create
 * the missing year — a decision about the school's accounts, which is not one
 * the posting code should be making silently on its own.
 *
 * accounting_period_id was already nullable, for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('fiscal_year_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deliberately not reversed.
        //
        // Going back to NOT NULL would need a value for every unattached entry,
        // and the only values available are wrong ones — which is the condition
        // this migration exists to make impossible. An installation that truly
        // wants the old column back should assign the years first, then alter
        // it by hand, having decided what those entries belong to.
    }
};
