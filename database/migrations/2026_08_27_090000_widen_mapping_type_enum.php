<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let default_account_mappings hold the payroll sub-types it already accepts.
 *
 * AccountMappingController has always validated mapping_type against eight
 * values — fee_category, income_category, expense_category, payroll,
 * payroll_tax, payroll_staff_payable, payroll_allowance, payroll_deduction —
 * but the column is an ENUM listing only the first four.
 *
 * MySQL outside strict mode does not reject a value missing from an ENUM; it
 * writes an empty string. So configuring a payroll tax mapping passed
 * validation, reported success, and silently stored a row belonging to no
 * mapping type at all — invisible to every query that filters by type, and
 * impossible to notice from the screen that wrote it.
 *
 * Widening the column to the set the application already promises is the fix.
 * Any row already truncated to '' is cleaned up first, taking its real type
 * from the description the seeder writes ("payroll_tax → 445").
 */
return new class extends Migration
{
    private const TYPES = [
        'fee_category',
        'income_category',
        'expense_category',
        'payroll',
        'payroll_tax',
        'payroll_staff_payable',
        'payroll_allowance',
        'payroll_deduction',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('default_account_mappings')) {
            return;
        }

        $list = implode(',', array_map(fn ($t) => "'" . $t . "'", self::TYPES));

        DB::statement(
            "ALTER TABLE `default_account_mappings`
             MODIFY `mapping_type` ENUM({$list}) NOT NULL"
        );

        // Recover rows the old ENUM blanked. The seeder stamps the intended
        // type into description ("payroll_tax → 445"), which is the only
        // surviving record of what the row was meant to be.
        $orphans = DB::table('default_account_mappings')
            ->where('mapping_type', '')
            ->get(['id', 'description']);

        foreach ($orphans as $orphan) {
            $intended = strtok((string) $orphan->description, ' ');

            if (in_array($intended, self::TYPES, true)) {
                DB::table('default_account_mappings')
                    ->where('id', $orphan->id)
                    ->update(['mapping_type' => $intended]);
            } else {
                // Nothing says what it was, and a mapping belonging to no type
                // is unreachable anyway.
                DB::table('default_account_mappings')->where('id', $orphan->id)->delete();
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('default_account_mappings')) {
            return;
        }

        // Rows on a type the narrow ENUM cannot hold would be blanked again on
        // the way down, so they go rather than becoming silent orphans.
        DB::table('default_account_mappings')
            ->whereIn('mapping_type', [
                'payroll_tax',
                'payroll_staff_payable',
                'payroll_allowance',
                'payroll_deduction',
            ])
            ->delete();

        DB::statement(
            "ALTER TABLE `default_account_mappings`
             MODIFY `mapping_type` ENUM('fee_category','income_category','expense_category','payroll') NOT NULL"
        );
    }
};
