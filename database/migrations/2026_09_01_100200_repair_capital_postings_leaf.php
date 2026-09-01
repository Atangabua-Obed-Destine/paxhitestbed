<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Guarantee that account 24 holds no postings of its own.
 *
 * 2026_09_01_100000 moves the six capital purchases off 24 onto the leaf 241,
 * but it returns early when 241 does not exist — and 241 is created by
 * OhadaMissingAccountsSeeder. On a deploy `migrate` runs before any seeder, so
 * on those installations that migration did nothing and was still recorded as
 * run. Seeding afterwards creates 241, which demotes 24 to a heading with its
 * postings still attached: a heading holding money is invisible to every report
 * that sums leaves, and the earlier migration can never run again to fix it.
 *
 * Editing the shipped migration would not help, because it is already recorded
 * as complete wherever the fault exists. This one repairs the state instead,
 * and states the invariant rather than assuming a starting point:
 *
 *     24 has children, therefore 24 must hold nothing itself.
 *
 * Safe on an installation that is already correct — it finds nothing to move
 * and does nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        // chart_of_accounts soft-deletes, and the raw query builder ignores
        // that. Without these guards a deleted account still looks present.
        $parent = DB::table('chart_of_accounts')
            ->where('account_code', '24')->whereNull('deleted_at')->first();

        if (!$parent) {
            // No chart of accounts yet. The seeder will create 24 and 241
            // together, with nothing on 24 to move.
            return;
        }

        $stranded = DB::table('journal_entry_lines')->where('account_id', $parent->id)->exists()
            || DB::table('default_account_mappings')
                ->where('debit_account_id', $parent->id)
                ->orWhere('credit_account_id', $parent->id)
                ->exists();

        if (!$stranded) {
            // Either the earlier migration already did its job, or this
            // installation never posted to 24.
            return;
        }

        DB::transaction(function () use ($parent) {
            $leaf = DB::table('chart_of_accounts')
                ->where('account_code', '241')->whereNull('deleted_at')->first();

            // account_code is unique across deleted rows too, so a 241 that was
            // deleted cannot simply be re-inserted. It is needed to hold the
            // postings — leaving them on a heading is the worse outcome — so it
            // is restored rather than duplicated.
            $trashed = DB::table('chart_of_accounts')
                ->where('account_code', '241')->whereNotNull('deleted_at')->first();

            if (!$leaf && $trashed) {
                DB::table('chart_of_accounts')->where('id', $trashed->id)->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);

                $leaf = DB::table('chart_of_accounts')->where('id', $trashed->id)->first();
            }

            if (!$leaf) {
                DB::table('chart_of_accounts')->insert([
                    'account_code' => '241',
                    'account_name' => 'Materiel et outillage',
                    'account_name_fr' => 'Matériel et outillage',
                    'parent_id' => $parent->id,
                    'class_number' => 2,
                    'account_type' => 'asset',
                    'account_category' => 'detail',
                    'normal_balance' => 'debit',
                    'is_system' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $leaf = DB::table('chart_of_accounts')->where('account_code', '241')->first();
            }

            // Only the account on each line changes. Amounts, dates and entries
            // are untouched, so the trial balance is unaffected and the 24
            // subtree still totals exactly what it did.
            DB::table('journal_entry_lines')
                ->where('account_id', $parent->id)
                ->update(['account_id' => $leaf->id]);

            // The mapping has to follow, or the next capital payment lands back
            // on the heading and the fault returns.
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
        // Not reversed. Moving the postings back onto 24 would put money on a
        // heading again, which is the fault this exists to remove.
    }
};
