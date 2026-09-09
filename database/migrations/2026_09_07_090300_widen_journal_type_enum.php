<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Let the journal say it is a payroll entry.
 *
 * PayrollAccountingService writes journal_type = 'payroll', but the column is
 * an enum that never contained that value, so MySQL stored an empty string
 * instead — silently, because the connection is not in strict mode. Every
 * payroll entry in the ledger carries journal_type = '' as a result, and
 * anything that filters or groups the journal by type misses payroll
 * altogether.
 *
 * The value was right and the column was wrong, so the column is widened
 * rather than the service changed. 'remittance' is added at the same time for
 * the tax payments this release introduces, so it does not repeat the fault.
 *
 * The backfill only touches rows whose type is empty and whose reference_type
 * already says what they are — it never reclassifies an entry that carries a
 * type of its own.
 */
return new class extends Migration
{
    private const TYPES = "'general','sales','purchase','cash','bank','adjustment','opening','closing','payroll','remittance'";

    public function up(): void
    {
        DB::statement("ALTER TABLE journal_entries MODIFY COLUMN journal_type ENUM(" . self::TYPES . ") NOT NULL DEFAULT 'general'");

        DB::table('journal_entries')
            ->where(function ($q) {
                $q->where('journal_type', '')->orWhereNull('journal_type');
            })
            ->whereIn('reference_type', ['payroll', 'payroll_reversal'])
            ->update(['journal_type' => 'payroll']);

        // Anything else left empty is not something this migration can name.
        // 'general' is the column's own default and the value every manual
        // entry already carries, so it is the honest fallback.
        DB::table('journal_entries')
            ->where(function ($q) {
                $q->where('journal_type', '')->orWhereNull('journal_type');
            })
            ->update(['journal_type' => 'general']);
    }

    public function down(): void
    {
        // Narrowing the enum again would blank the rows this repaired, which
        // is the fault it exists to fix.
    }
};
