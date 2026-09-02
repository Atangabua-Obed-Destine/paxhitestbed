<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Put two tax rows back where the calculator can read them correctly.
 *
 * The payroll fetches taxes in three buckets: those with a tax_group_id, and
 * ungrouped ones split by is_dependent. The dependency logic — "take a
 * percentage of another tax rather than of salary" — exists only in the third
 * bucket. A row's tax_group_id therefore decides which rules apply to it, and
 * two rows were in the wrong one.
 *
 *   Additional Council Tax is a surcharge on the income tax: is_dependent = 1,
 *   depends_on tax_group 1, 10%. But it also carried tax_group_id = 3, so it
 *   was handled as a group, and the group path passes the SALARY and never
 *   reads is_dependent. It charged 10% of salary — 18,000 on a 180,000 wage
 *   instead of 793 — making the surcharge larger than the tax it surcharges.
 *
 *   Local Development Tax B2 lost its tax_group_id, so it was processed as a
 *   standalone tax and printed as its own payslip line instead of under its
 *   group. It gives the right figure today only because the group's B1 returns
 *   zero across B2's range; it would double-charge the moment B1 changed.
 *
 * Group 3 then holds nothing — it only ever held this one row — so it is
 * deactivated rather than deleted, leaving its history readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Keyed on the dependency configuration rather than an id, so this
            // corrects any row expressing the same contradiction.
            DB::table('tax_settings')
                ->where('is_dependent', 1)
                ->whereNotNull('tax_group_id')
                ->update(['tax_group_id' => null, 'updated_at' => now()]);

            $group = DB::table('tax_groups')->where('title', 'Local Development Tax')->first();

            if ($group) {
                DB::table('tax_settings')
                    ->where('title', 'LIKE', 'Local Development Tax%B2')
                    ->whereNull('tax_group_id')
                    ->where('is_dependent', 0)
                    ->update(['tax_group_id' => $group->id, 'updated_at' => now()]);
            }

            // Any group left with no brackets at all can only contribute zero,
            // and an empty group on the settings screen invites someone to file
            // a bracket back into it.
            $emptyGroups = DB::table('tax_groups')
                ->where('status', 1)
                ->whereNotIn('id', function ($q) {
                    $q->select('tax_group_id')->from('tax_settings')->whereNotNull('tax_group_id');
                })
                ->pluck('id');

            if ($emptyGroups->isNotEmpty()) {
                DB::table('tax_groups')->whereIn('id', $emptyGroups)
                    ->update(['status' => 0, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Not reversed. Restoring the rows would reinstate a surcharge charged
        // on salary instead of on the tax it surcharges.
    }
};
