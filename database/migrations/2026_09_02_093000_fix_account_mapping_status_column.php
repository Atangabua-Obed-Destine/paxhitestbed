<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make default_account_mappings.status hold what every reader asks it for.
 *
 * The column is tinyint(1), but every writer stores the string 'active' and
 * every reader queries for it — the model's own scopes are
 * where('status', 'active') and where('status', 'inactive').
 *
 * MySQL casts a non-numeric string to 0 when comparing against an integer
 * column, so:
 *
 *   writing 'active'   stored 0
 *   reading 'active'   matched every row with status = 0
 *   reading 'inactive' matched exactly the same rows
 *
 * activeMappings() and inactiveMappings() therefore returned identical sets,
 * and a mapping genuinely enabled as 1 would have been invisible to both. The
 * two errors cancelled, which is why nothing looked wrong — until the payroll
 * used a mapping it believed was active to credit withheld tax to the VAT
 * account.
 *
 * All 30 rows are 0, which is what 'active' stored, and the system has been
 * treating every one of them as active. Converting them to 'active' therefore
 * preserves current behaviour exactly rather than changing which mappings
 * apply. Nothing can be recovered about intent beyond that: 'inactive' stored
 * 0 as well, so a deliberately disabled mapping is indistinguishable from an
 * enabled one, and after this migration disabling actually works.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Read before altering: once the column is a string the old integers
        // are gone.
        $wasZero = DB::table('default_account_mappings')->where('status', 0)->pluck('id');
        $wasOne = DB::table('default_account_mappings')->where('status', 1)->pluck('id');

        Schema::table('default_account_mappings', function ($table) {
            $table->string('status', 20)->default('active')->change();
        });

        DB::transaction(function () use ($wasZero, $wasOne) {
            if ($wasZero->isNotEmpty()) {
                DB::table('default_account_mappings')->whereIn('id', $wasZero)
                    ->update(['status' => 'active', 'updated_at' => now()]);
            }

            // A row stored as 1 could only have been set numerically, and was
            // invisible to every 'active' lookup. Enabling it now would change
            // which mappings apply, so it stays off.
            if ($wasOne->isNotEmpty()) {
                DB::table('default_account_mappings')->whereIn('id', $wasOne)
                    ->update(['status' => 'inactive', 'updated_at' => now()]);
            }

            // With the column fixed, this mapping is genuinely consulted for the
            // first time — and it credits withheld staff tax to 571 Caisse
            // Principale, which is cash, not a liability. Tax withheld from pay
            // is money held on the State's behalf until it is remitted, so it
            // belongs in 443 Etat - Retenue.
            $withholding = DB::table('chart_of_accounts')
                ->where('account_code', '443')->whereNull('deleted_at')->first();

            if ($withholding) {
                DB::table('default_account_mappings')
                    ->where('mapping_type', 'payroll_tax')
                    ->update(['credit_account_id' => $withholding->id, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        DB::table('default_account_mappings')->update(['status' => 0]);

        Schema::table('default_account_mappings', function ($table) {
            $table->tinyInteger('status')->default(0)->change();
        });
    }
};
