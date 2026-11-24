<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Link payroll and staff-loan reversals to the entries they cancel.
 *
 * Until now those reversals did not record which entry they reversed, and the
 * entry they reversed was never marked reversed. The money was right — the
 * reversal mirrors the original line for line — but the original stayed
 * "live", so once the payroll was paid again it had two live entries and the
 * ledger health page reported the salary as posted twice.
 *
 * Each unlinked reversal is matched to the latest earlier entry for the same
 * payroll or loan that no other reversal already claims and whose totals it
 * mirrors. Anything that does not match exactly is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pairs = ['payroll_reversal' => 'payroll', 'staff_loan_reversal' => 'staff_loan'];

        foreach ($pairs as $reversalType => $originalType) {
            $reversals = DB::table('journal_entries')
                ->where('reference_type', $reversalType)
                ->whereNull('reversed_entry_id')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            foreach ($reversals as $reversal) {
                $original = DB::table('journal_entries as o')
                    ->where('o.reference_type', $originalType)
                    ->where('o.reference_id', $reversal->reference_id)
                    ->where('o.id', '<', $reversal->id)
                    ->whereNull('o.deleted_at')
                    ->whereRaw('ABS(o.total_debit - ?) < 0.01', [$reversal->total_credit])
                    ->whereNotExists(fn ($q) => $q->from('journal_entries as r')
                        ->whereColumn('r.reversed_entry_id', 'o.id')
                        ->whereNull('r.deleted_at'))
                    ->orderByDesc('o.id')
                    ->first();

                if (!$original) {
                    continue;
                }

                DB::table('journal_entries')->where('id', $reversal->id)->update(['reversed_entry_id' => $original->id]);
                DB::table('journal_entries')->where('id', $original->id)->update(['is_reversed' => 1]);
            }
        }
    }

    public function down(): void
    {
        // The links record a fact about the books; there is nothing to undo.
    }
};
