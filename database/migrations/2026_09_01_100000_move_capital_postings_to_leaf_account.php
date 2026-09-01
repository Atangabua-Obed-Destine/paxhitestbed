<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move the general capital purchases off 24 onto its new leaf 241.
 *
 * 24 Materiel carried six postings directly. Once it gained children it had to
 * become a heading, and a heading that still holds postings is invisible to
 * every report that sums leaves — the six purchases would have vanished from
 * the sheet while still sitting in the trial balance.
 *
 * Only the account on the line changes. Amounts, dates, entries and the trial
 * balance are untouched, and the 24 subtree totals exactly what it did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('chart_of_accounts')->where('account_code', '24')->first();
        $leaf = DB::table('chart_of_accounts')->where('account_code', '241')->first();

        if (!$parent || !$leaf) {
            // Run OhadaMissingAccountsSeeder first; nothing to move without 241.
            return;
        }

        DB::transaction(function () use ($parent, $leaf) {
            DB::table('journal_entry_lines')
                ->where('account_id', $parent->id)
                ->update(['account_id' => $leaf->id]);

            // The mapping has to follow, or the next capital payment lands back
            // on the heading and the problem returns.
            DB::table('default_account_mappings')
                ->where('debit_account_id', $parent->id)
                ->update(['debit_account_id' => $leaf->id]);

            DB::table('default_account_mappings')
                ->where('credit_account_id', $parent->id)
                ->update(['credit_account_id' => $leaf->id]);

            DB::table('chart_of_accounts')
                ->where('id', $parent->id)
                ->update(['account_category' => 'heading']);
        });
    }

    public function down(): void
    {
        $parent = DB::table('chart_of_accounts')->where('account_code', '24')->first();
        $leaf = DB::table('chart_of_accounts')->where('account_code', '241')->first();

        if (!$parent || !$leaf) {
            return;
        }

        DB::transaction(function () use ($parent, $leaf) {
            DB::table('journal_entry_lines')
                ->where('account_id', $leaf->id)
                ->update(['account_id' => $parent->id]);

            DB::table('default_account_mappings')
                ->where('debit_account_id', $leaf->id)
                ->update(['debit_account_id' => $parent->id]);

            DB::table('default_account_mappings')
                ->where('credit_account_id', $leaf->id)
                ->update(['credit_account_id' => $parent->id]);

            DB::table('chart_of_accounts')
                ->where('id', $parent->id)
                ->update(['account_category' => 'detail']);
        });
    }
};
