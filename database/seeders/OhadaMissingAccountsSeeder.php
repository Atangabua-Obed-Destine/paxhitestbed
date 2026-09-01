<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Database\Seeders\Concerns\SeedsWithoutOverwriting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Accounts the chart was missing.
 *
 * Four of these are not tidiness. They are counterparts to things that already
 * exist and are broken without them:
 *
 *   683 depreciates a vehicle asset account that was never created (245).
 *   The sheet carries a bad debts line with no 65 to post it to.
 *   It carries two provision lines with no 69 / 79.
 *   The canteen, uniforms, books and phone booth profit centres trade, but
 *     there was no 70x for trading income — only fee and grant accounts.
 *
 * The rest (16, 419, 585, 641, 78) are places the next transaction would have
 * had nowhere to land.
 *
 * Order matters: a parent is looked up by code, so a new parent is listed
 * before its children. Follows OhadaDetailAccountsSeeder, including demoting a
 * parent to a heading once it has children, so postings only reach leaves.
 *
 * Create-only: an account that already exists is left completely alone, name,
 * category and is_active included. Re-running must never reactivate an account
 * somebody switched off, nor make a heading postable again.
 */
class OhadaMissingAccountsSeeder extends Seeder
{
    use SeedsWithoutOverwriting;

    public function run(): void
    {

        foreach ($this->accounts() as [$code, $name, $nameFr, $parentCode, $type, $normal]) {
            $parent = ChartOfAccount::where('account_code', $parentCode)->first();

            if (!$parent) {
                $this->command?->warn("Skipped {$code}: parent {$parentCode} not found. Run OhadaChartOfAccountsSeeder first.");
                continue;
            }

            $this->createIfAbsent(
                ChartOfAccount::class,
                ['account_code' => $code],
                [
                    'account_name' => $name,
                    'account_name_fr' => $nameFr,
                    'parent_id' => $parent->id,
                    'class_number' => (int) substr($code, 0, 1),
                    'account_type' => $type,
                    'account_category' => 'detail',
                    'normal_balance' => $normal,
                    'is_system' => false,
                    'is_active' => true,
                ],
                $code . ' ' . $name
            );

            $this->demoteParent($parent);
        }

        $this->command?->info(sprintf(
            'Missing accounts: %d created, %d already present, %d postable in total.',
            $this->createdCount,
            $this->skippedCount,
            ChartOfAccount::postable()->count()
        ));
    }

    /**
     * An account with children must not accept postings itself.
     *
     * Otherwise the same figure sits on both the summary and the detail and is
     * counted twice by anything that adds a heading to its children — or, worse,
     * a posting left on the parent vanishes from anything that sums leaves.
     *
     * Guarded, because this is the one place the seeder is allowed to change an
     * existing row: a parent that already holds postings or a mapping is left
     * alone. Demoting it would hide real money, and moving those postings is a
     * decision for whoever knows what they were, not for a seeder.
     */
    protected function demoteParent(ChartOfAccount $parent): void
    {
        if ($parent->account_category === 'heading') {
            return;
        }

        $inUse = DB::table('journal_entry_lines')->where('account_id', $parent->id)->exists()
            || DB::table('default_account_mappings')
                ->where('debit_account_id', $parent->id)
                ->orWhere('credit_account_id', $parent->id)
                ->exists();

        if ($inUse) {
            $this->noteUnresolved($parent->account_code . ' still holds postings — left postable');
            return;
        }

        $parent->update(['account_category' => 'heading']);
    }

    /**
     * [code, name, name_fr, parent_code, account_type, normal_balance]
     */
    protected function accounts(): array
    {
        return [
            // --- 1 Ressources durables --------------------------------------
            // Diocesan loans and any bank facility. Without this a borrowing
            // has to be booked as income, which overstates the year.
            ['16', 'Emprunts et dettes financieres', 'Emprunts et dettes financières', '1', 'liability', 'credit'],

            // --- 2 Immobilisations ------------------------------------------
            // 24 itself carried six capital purchases. It cannot stay postable
            // once it has children, so 241 is created to hold that general
            // equipment and the postings are moved onto it by the migration
            // that accompanies this seeder. The subtree total is unchanged.
            ['241', 'Materiel et outillage', 'Matériel et outillage', '24', 'asset', 'debit'],

            // 683 already charges depreciation on vehicles; the asset it
            // depreciates did not exist.
            ['245', 'Materiel de transport', 'Matériel de transport', '24', 'asset', 'debit'],

            // --- 4 Tiers ----------------------------------------------------
            // Fees paid before the instalment they belong to is raised. Money
            // held, not yet earned — a liability, not income.
            ['419', 'Clients crediteurs et avances recues', 'Clients créditeurs et avances reçues', '41', 'liability', 'credit'],

            // --- 5 Tresorerie -----------------------------------------------
            // Cash moved between the safe and the bank touches two cash
            // accounts. Routed through 585 the two halves can be matched and
            // the transfer is not mistaken for income and expenditure.
            ['58', 'Virements internes', 'Virements internes', '5', 'asset', 'debit'],
            ['585', 'Virements de fonds', 'Virements de fonds', '58', 'asset', 'debit'],

            // --- 64 Impots et taxes -----------------------------------------
            ['641', 'Impots et taxes directs', 'Impôts et taxes directs', '64', 'expense', 'debit'],
            ['645', 'Impots et taxes indirects', 'Impôts et taxes indirects', '64', 'expense', 'debit'],
            ['646', 'Droits d enregistrement et timbres', "Droits d'enregistrement et timbres", '64', 'expense', 'debit'],
            ['648', 'Autres impots et taxes', 'Autres impôts et taxes', '64', 'expense', 'debit'],

            // --- 65 Autres charges ------------------------------------------
            // Fees written off once they are accepted as uncollectable, and
            // cash differences found at a count.
            ['651', 'Pertes sur creances clients', 'Pertes sur créances clients', '6', 'expense', 'debit'],
            ['658', 'Charges diverses', 'Charges diverses', '6', 'expense', 'debit'],

            // --- 69 Dotations aux provisions --------------------------------
            // Fees judged unlikely to be collected, and known risks not yet
            // settled, charged to the year they arise in.
            ['691', 'Dotations aux provisions d exploitation', "Dotations aux provisions d'exploitation", '6', 'expense', 'debit'],
            ['697', 'Dotations aux provisions pour risques', 'Dotations aux provisions pour risques', '6', 'expense', 'debit'],

            // --- 70 Ventes --------------------------------------------------
            // The trading profit centres. Their takings are not school fees and
            // must not swell the fee total on the sheet.
            ['701', 'Ventes de marchandises', 'Ventes de marchandises', '70', 'revenue', 'credit'],
            ['706', 'Services vendus', 'Services vendus', '70', 'revenue', 'credit'],

            // --- 7 Produits -------------------------------------------------
            // A cost carried for someone else and then recovered — a refund of
            // the charge, not new income.
            ['78', 'Transferts de charges', 'Transferts de charges', '7', 'revenue', 'credit'],

            // The other half of 69: a provision released when the risk passes
            // or the fee is paid after all.
            ['79', 'Reprises de provisions', 'Reprises de provisions', '7', 'revenue', 'credit'],
        ];
    }
}
