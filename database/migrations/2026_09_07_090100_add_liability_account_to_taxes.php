<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Say which body each tax is owed to.
 *
 * Every franc withheld from staff pay is currently credited to a single
 * account, 443 Etat - Retenue, including the employee's CNPS 4.2% — which is
 * owed to CNPS, not to the State. On one 180,000 salary that is 7,560 sitting
 * in the wrong liability, and it means the question "what do we owe CNPS?"
 * cannot be answered from the ledger at all.
 *
 * The account is the authority. OHADA already separates them — 431 Organismes
 * Sociaux, 443 Etat — so no new taxonomy is invented here: a tax points at the
 * account it accrues to, and the remittance screen groups by that account. A
 * school that later wants CRTV or the housing fund settled separately creates
 * those accounts and repoints those two taxes; nothing needs rewriting.
 *
 * Defaults seeded here: CNPS to 431, everything else to 443. A tax left unset
 * is not an error — the posting code falls back to findTaxPayableAccount(),
 * which is exactly what it does today, so an unconfigured tax behaves as it
 * always has rather than failing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('liability_account_id')->nullable()->after('status');
        });

        Schema::table('tax_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('liability_account_id')->nullable()->after('status');
        });

        $state = $this->account('443');
        $social = $this->account('431');

        if (!$state && !$social) {
            // Nothing to point at. Leaving every row null is the honest
            // outcome — the posting code's own fallback still applies.
            return;
        }

        DB::transaction(function () use ($state, $social) {
            if ($social) {
                // 431 is Organismes Sociaux — the social bodies, not the
                // State. Two taxes belong there: the social insurance
                // contribution and the employment fund levy, which is
                // collected alongside it rather than through the tax office.
                //
                // Matched on name rather than id, because ids differ between
                // installations while these titles do not. The employment fund
                // matters as much as the insurance one: its employer share
                // already posts to 431 today as part of the lump employer
                // charge, so leaving it on 443 would be a silent change of
                // where an existing figure lands.
                $socialTitles = ['%Insurance Fund%', '%CNPS%', '%Social%Insurance%', '%Employment Fund%'];

                DB::table('tax_settings')
                    ->where(function ($q) use ($socialTitles) {
                        foreach ($socialTitles as $title) {
                            $q->orWhere('title', 'LIKE', $title);
                        }
                    })
                    ->update(['liability_account_id' => $social, 'updated_at' => now()]);

                DB::table('tax_groups')
                    ->where(function ($q) use ($socialTitles) {
                        $q->where('code', 'CNPS');
                        foreach ($socialTitles as $title) {
                            $q->orWhere('title', 'LIKE', $title);
                        }
                    })
                    ->update(['liability_account_id' => $social, 'updated_at' => now()]);
            }

            if ($state) {
                DB::table('tax_settings')->whereNull('liability_account_id')
                    ->update(['liability_account_id' => $state, 'updated_at' => now()]);

                DB::table('tax_groups')->whereNull('liability_account_id')
                    ->update(['liability_account_id' => $state, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tax_groups', function (Blueprint $table) {
            $table->dropColumn('liability_account_id');
        });

        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropColumn('liability_account_id');
        });
    }

    /** The id of a postable account by code, or null when it is absent. */
    private function account(string $code): ?int
    {
        $row = DB::table('chart_of_accounts')
            ->where('account_code', $code)
            ->where('account_category', 'detail')
            ->where('is_active', 1)
            ->first();

        return $row ? (int) $row->id : null;
    }
};
