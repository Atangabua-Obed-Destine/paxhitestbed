<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An account with children must not itself accept postings.
 *
 * 60, 62, 63, 68 and 75 each gained children but stayed marked postable, so
 * they still appear in every account picker. Nothing has been posted to them
 * yet — but anything posted there would be counted twice by a report that adds
 * a heading to its children, and lost entirely by one that sums leaves, which
 * is what the budget sheet does. The trap is that neither failure raises an
 * error; a total simply reads wrong.
 *
 * Written as a rule rather than a list of five codes so it also catches the
 * next parent that acquires a child. An account that already holds postings is
 * deliberately left alone: demoting it would hide real money, and moving those
 * postings is a decision for whoever knows what they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parentIds = DB::table('chart_of_accounts')
            ->whereNotNull('parent_id')->distinct()->pluck('parent_id');

        $candidates = DB::table('chart_of_accounts')
            ->whereIn('id', $parentIds)
            ->where('account_category', '!=', 'heading')
            ->get();

        foreach ($candidates as $account) {
            $hasPostings = DB::table('journal_entry_lines')
                ->where('account_id', $account->id)->exists();

            $isMapped = DB::table('default_account_mappings')
                ->where('debit_account_id', $account->id)
                ->orWhere('credit_account_id', $account->id)
                ->exists();

            if ($hasPostings || $isMapped) {
                continue;
            }

            DB::table('chart_of_accounts')
                ->where('id', $account->id)
                ->update(['account_category' => 'heading']);
        }
    }

    public function down(): void
    {
        // Not reversed. Which accounts were headings beforehand is not
        // recorded, and restoring the wrong ones to postable would reopen the
        // very hole this closes.
    }
};
