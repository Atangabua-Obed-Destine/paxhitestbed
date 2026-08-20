<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

/**
 * Adds the SYSCOHADA detail accounts the base chart leaves out.
 *
 * The base seeder stops at two-digit accounts for most of class 6, which would
 * put electricity, stationery, cleaning and insurance all on "60 Achats" — a
 * ledger that balances but explains nothing. These are the standard OHADA
 * subdivisions, chosen to give every line of the budget sheet somewhere
 * meaningful to post.
 *
 * Parents are demoted to headings as their children appear, so postings can
 * only land on a leaf account.
 *
 * Idempotent: safe to re-run.
 */
class OhadaDetailAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->accounts() as [$code, $name, $nameFr, $parentCode, $type, $normal]) {
            $parent = ChartOfAccount::where('account_code', $parentCode)->first();

            if (!$parent) {
                $this->command?->warn("Skipped {$code}: parent {$parentCode} not found. Run OhadaChartOfAccountsSeeder first.");
                continue;
            }

            ChartOfAccount::updateOrCreate(
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
                ]
            );

            // A parent that now has children must not accept postings itself,
            // otherwise the same cost could sit on both the summary and the
            // detail and be counted twice in any report that sums leaves.
            if ($parent->account_category !== 'heading') {
                $parent->update(['account_category' => 'heading']);
            }
        }

        $this->command?->info('Detail accounts: ' . ChartOfAccount::postable()->count() . ' postable accounts now available.');
    }

    /**
     * [code, name, name_fr, parent_code, account_type, normal_balance]
     */
    protected function accounts(): array
    {
        return [
            // --- 60 Achats -------------------------------------------------
            ['601', 'Fournitures de bureau', 'Fournitures de bureau', '60', 'expense', 'debit'],
            ['602', 'Materiel pedagogique', 'Matériel pédagogique', '60', 'expense', 'debit'],
            ['605', 'Eau et electricite', 'Eau et électricité', '60', 'expense', 'debit'],
            ['606', 'Carburants et lubrifiants', 'Carburants et lubrifiants', '60', 'expense', 'debit'],
            ['607', 'Produits d entretien et hygiene', "Produits d'entretien et hygiène", '60', 'expense', 'debit'],
            ['608', 'Achats de projets agricoles', 'Achats de projets agricoles', '60', 'expense', 'debit'],

            // --- 62 Services Exterieurs ------------------------------------
            ['622', 'Locations', 'Locations', '62', 'expense', 'debit'],
            ['624', 'Entretien et reparations', 'Entretien et réparations', '62', 'expense', 'debit'],
            ['625', 'Primes d assurance', "Primes d'assurance", '62', 'expense', 'debit'],
            ['626', 'Telecommunications et internet', 'Télécommunications et internet', '62', 'expense', 'debit'],
            ['627', 'Services bancaires', 'Services bancaires', '62', 'expense', 'debit'],
            ['628', 'Autres services exterieurs', 'Autres services extérieurs', '62', 'expense', 'debit'],

            // --- 63 Autres Services ---------------------------------------
            ['631', 'Frais de formation et seminaires', 'Frais de formation et séminaires', '63', 'expense', 'debit'],
            ['632', 'Frais de recrutement', 'Frais de recrutement', '63', 'expense', 'debit'],
            ['633', 'Frais de transport et deplacements', 'Frais de transport et déplacements', '63', 'expense', 'debit'],
            ['634', 'Receptions et ceremonies', 'Réceptions et cérémonies', '63', 'expense', 'debit'],
            ['635', 'Publicite et communication', 'Publicité et communication', '63', 'expense', 'debit'],
            ['636', 'Securite et gardiennage', 'Sécurité et gardiennage', '63', 'expense', 'debit'],
            ['637', 'Frais medicaux et laboratoire', 'Frais médicaux et laboratoire', '63', 'expense', 'debit'],
            ['638', 'Documentation et bibliotheque', 'Documentation et bibliothèque', '63', 'expense', 'debit'],

            // --- 66 Charges de Personnel (661/664 already exist) -----------
            ['662', 'Indemnites et primes', 'Indemnités et primes', '66', 'expense', 'debit'],
            ['663', 'Oeuvres sociales du personnel', 'Œuvres sociales du personnel', '66', 'expense', 'debit'],

            // --- 68 Dotations aux amortissements --------------------------
            ['681', 'Amortissement du materiel', 'Amortissement du matériel', '68', 'expense', 'debit'],
            ['682', 'Amortissement des batiments', 'Amortissement des bâtiments', '68', 'expense', 'debit'],
            ['683', 'Amortissement du materiel de transport', 'Amortissement du matériel de transport', '68', 'expense', 'debit'],

            // --- 75 Autres Produits ---------------------------------------
            ['751', 'Produits des projets', 'Produits des projets', '75', 'revenue', 'credit'],
            ['758', 'Produits divers', 'Produits divers', '75', 'revenue', 'credit'],

            // --- 41 Eleves et Parents -------------------------------------
            ['412', 'Avances au personnel et tiers', 'Avances au personnel et tiers', '4', 'asset', 'debit'],
        ];
    }
}
