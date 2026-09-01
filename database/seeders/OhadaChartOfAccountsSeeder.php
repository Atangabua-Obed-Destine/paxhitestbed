<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Concerns\SeedsWithoutOverwriting;
use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class OhadaChartOfAccountsSeeder extends Seeder
{
    use SeedsWithoutOverwriting;

    /**
     * Run the database seeds - OHADA Chart of Accounts for Cameroon Schools
     */
    public function run(): void
    {
        // Never truncate. Once the ledger is live, wiping the chart would orphan
        // every journal line, mapping and budget-line link that points at it.
        $accounts = $this->getOhadaAccounts();

        // First pass: create the accounts that are missing. An account that is
        // already there is not touched at all — not its name, not its category,
        // not is_active. Overwriting account_category in particular used to
        // turn headings back into postable accounts, which strands postings on
        // a parent where no leaf-summing report can see them.
        $accountMap = [];
        $freshlyCreated = [];

        foreach ($accounts as $account) {
            $code = $account['account_code'];
            unset($account['parent_code']);

            $row = $this->createIfAbsent(
                ChartOfAccount::class,
                ['account_code' => $code],
                $account,
                $code . ' ' . ($account['account_name'] ?? '')
            );

            $accountMap[$row->account_code] = $row->id;

            if ($row->wasRecentlyCreated) {
                $freshlyCreated[$code] = true;
            }
        }

        // Second pass: link parents — but only for accounts this run created.
        // Re-pointing an existing account's parent would move it in the tree,
        // and the tree is what every subtotal on the sheet is built from.
        foreach ($accounts as $account) {
            $code = $account['account_code'];

            if (!isset($freshlyCreated[$code], $account['parent_code'])) {
                continue;
            }

            if (isset($accountMap[$account['parent_code']])) {
                ChartOfAccount::where('account_code', $code)
                    ->update(['parent_id' => $accountMap[$account['parent_code']]]);
            }
        }

        $this->command?->info(sprintf(
            'OHADA chart: %d created, %d already present, %d defined.',
            $this->createdCount,
            $this->skippedCount,
            count($accounts)
        ));
    }

    /**
     * The account definitions, for callers that need the labels without
     * seeding — `defaults:install --refresh-names` corrects names from these.
     */
    public function definedAccounts(): array
    {
        return $this->getOhadaAccounts();
    }

    private function getOhadaAccounts()
    {
        return [
            // CLASS 1: Capital/Equity
            ['account_code' => '1', 'account_name' => 'Capital Accounts', 'account_name_fr' => 'Comptes de Capitaux', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '10', 'account_name' => 'Capital', 'account_name_fr' => 'Capital', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '1'],
            ['account_code' => '101', 'account_name' => 'Capital Social', 'account_name_fr' => 'Capital Social', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '10'],
            ['account_code' => '11', 'account_name' => 'Reserves', 'account_name_fr' => 'Réserves', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '1'],
            ['account_code' => '111', 'account_name' => 'Reserve Legale', 'account_name_fr' => 'Réserve Légale', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '11'],
            ['account_code' => '12', 'account_name' => 'Resultat Net', 'account_name_fr' => 'Résultat Net', 'class_number' => 1, 'account_type' => 'equity', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '1'],
            
            // CLASS 2: Fixed Assets
            ['account_code' => '2', 'account_name' => 'Fixed Assets', 'account_name_fr' => 'Immobilisations', 'class_number' => 2, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '22', 'account_name' => 'Terrains', 'account_name_fr' => 'Terrains', 'class_number' => 2, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '2'],
            ['account_code' => '23', 'account_name' => 'Batiments', 'account_name_fr' => 'Bâtiments', 'class_number' => 2, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '2'],
            ['account_code' => '24', 'account_name' => 'Materiel', 'account_name_fr' => 'Matériel', 'class_number' => 2, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '2'],
            ['account_code' => '28', 'account_name' => 'Amortissements', 'account_name_fr' => 'Amortissements', 'class_number' => 2, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '2'],
            
            // CLASS 3: Inventory
            ['account_code' => '3', 'account_name' => 'Inventory', 'account_name_fr' => 'Stocks', 'class_number' => 3, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '31', 'account_name' => 'Marchandises', 'account_name_fr' => 'Marchandises', 'class_number' => 3, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '3'],
            
            // CLASS 4: Third Parties
            ['account_code' => '4', 'account_name' => 'Third Parties', 'account_name_fr' => 'Comptes de Tiers', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '40', 'account_name' => 'Fournisseurs', 'account_name_fr' => 'Fournisseurs', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '4'],
            ['account_code' => '41', 'account_name' => 'Eleves et Parents', 'account_name_fr' => 'Élèves et Parents', 'class_number' => 4, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '4'],
            ['account_code' => '411', 'account_name' => 'Scolarites a Recevoir', 'account_name_fr' => 'Scolarités à Recevoir', 'class_number' => 4, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '41'],
            ['account_code' => '42', 'account_name' => 'Personnel', 'account_name_fr' => 'Personnel', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '4'],
            ['account_code' => '421', 'account_name' => 'Personnel - Salaires dus', 'account_name_fr' => 'Personnel - Salaires dus', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '42'],
            ['account_code' => '43', 'account_name' => 'Organismes Sociaux', 'account_name_fr' => 'Organismes Sociaux', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '4'],
            ['account_code' => '431', 'account_name' => 'CNPS', 'account_name_fr' => 'CNPS', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '43'],
            ['account_code' => '44', 'account_name' => 'Etat', 'account_name_fr' => 'État', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '4'],
            ['account_code' => '441', 'account_name' => 'Etat - TVA', 'account_name_fr' => 'État - TVA', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '44'],
            ['account_code' => '443', 'account_name' => 'Etat - Retenue', 'account_name_fr' => 'État - Retenue', 'class_number' => 4, 'account_type' => 'liability', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '44'],
            
            // CLASS 5: Cash & Banks
            ['account_code' => '5', 'account_name' => 'Cash & Banks', 'account_name_fr' => 'Trésorerie', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '52', 'account_name' => 'Banques', 'account_name_fr' => 'Banques', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '5'],
            ['account_code' => '521', 'account_name' => 'Banques Locales', 'account_name_fr' => 'Banques Locales', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '52'],
            ['account_code' => '53', 'account_name' => 'Mobile Money', 'account_name_fr' => 'Mobile Money', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '5'],
            ['account_code' => '57', 'account_name' => 'Caisse', 'account_name_fr' => 'Caisse', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '5'],
            ['account_code' => '571', 'account_name' => 'Caisse Principale', 'account_name_fr' => 'Caisse Principale', 'class_number' => 5, 'account_type' => 'asset', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '57'],
            
            // CLASS 6: Expenses
            ['account_code' => '6', 'account_name' => 'Expenses', 'account_name_fr' => 'Charges', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '60', 'account_name' => 'Achats', 'account_name_fr' => 'Achats', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '61', 'account_name' => 'Transports', 'account_name_fr' => 'Transports', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '62', 'account_name' => 'Services Exterieurs', 'account_name_fr' => 'Services Extérieurs', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '63', 'account_name' => 'Autres Services', 'account_name_fr' => 'Autres Services', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '64', 'account_name' => 'Impots et Taxes', 'account_name_fr' => 'Impôts et Taxes', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '66', 'account_name' => 'Charges de Personnel', 'account_name_fr' => 'Charges de Personnel', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '6'],
            ['account_code' => '661', 'account_name' => 'Salaires', 'account_name_fr' => 'Salaires', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => true, 'parent_code' => '66'],
            ['account_code' => '664', 'account_name' => 'Charges Sociales', 'account_name_fr' => 'Charges Sociales', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '66'],
            ['account_code' => '67', 'account_name' => 'Frais Financiers', 'account_name_fr' => 'Frais Financiers', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            ['account_code' => '68', 'account_name' => 'Dotations Amortissements', 'account_name_fr' => 'Dotations Amortissements', 'class_number' => 6, 'account_type' => 'expense', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '6'],
            
            // CLASS 7: Revenue
            ['account_code' => '7', 'account_name' => 'Revenue', 'account_name_fr' => 'Produits', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_id' => null],
            ['account_code' => '70', 'account_name' => 'Ventes', 'account_name_fr' => 'Ventes', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '7'],
            ['account_code' => '71', 'account_name' => 'Scolarites', 'account_name_fr' => 'Scolarités', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '7'],
            ['account_code' => '711', 'account_name' => 'Frais de Scolarite', 'account_name_fr' => 'Frais de Scolarité', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => true, 'parent_code' => '71'],
            ['account_code' => '712', 'account_name' => 'Frais d\'Inscription', 'account_name_fr' => 'Frais d\'Inscription', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '71'],
            ['account_code' => '713', 'account_name' => 'Frais d\'Examen', 'account_name_fr' => 'Frais d\'Examen', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '71'],
            ['account_code' => '73', 'account_name' => 'Subventions', 'account_name_fr' => 'Subventions', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '7'],
            ['account_code' => '75', 'account_name' => 'Autres Produits', 'account_name_fr' => 'Autres Produits', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '7'],
            ['account_code' => '77', 'account_name' => 'Revenus Financiers', 'account_name_fr' => 'Revenus Financiers', 'class_number' => 7, 'account_type' => 'revenue', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '7'],
            
            // CLASS 8: Other Results
            ['account_code' => '8', 'account_name' => 'Other Results', 'account_name_fr' => 'Autres Charges et Produits', 'class_number' => 8, 'account_type' => 'other', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => false, 'parent_id' => null],
            ['account_code' => '81', 'account_name' => 'Valeurs Comptables', 'account_name_fr' => 'Valeurs Comptables', 'class_number' => 8, 'account_type' => 'other', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '8'],
            ['account_code' => '82', 'account_name' => 'Produits de Cessions', 'account_name_fr' => 'Produits de Cessions', 'class_number' => 8, 'account_type' => 'other', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '8'],
            
            // CLASS 9: Analytical Accounting & Commitments
            ['account_code' => '9', 'account_name' => 'Analytical Accounts', 'account_name_fr' => 'Comptes Analytiques', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => false, 'parent_id' => null],
            
            // Commitments and Off-Balance Sheet Items for Schools
            ['account_code' => '90', 'account_name' => 'Commitments Given', 'account_name_fr' => 'Engagements Donnés', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '9'],
            ['account_code' => '901', 'account_name' => 'Purchase Commitments', 'account_name_fr' => 'Engagements d\'Achat', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '90'],
            ['account_code' => '902', 'account_name' => 'Service Contracts', 'account_name_fr' => 'Contrats de Service', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '90'],
            
            ['account_code' => '91', 'account_name' => 'Commitments Received', 'account_name_fr' => 'Engagements Reçus', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '9'],
            ['account_code' => '911', 'account_name' => 'Grants Committed', 'account_name_fr' => 'Subventions Engagées', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '91'],
            ['account_code' => '912', 'account_name' => 'Tuition Pre-payments', 'account_name_fr' => 'Avances de Scolarité', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '91'],
            
            // Analytical cost and revenue centres — OHADA class 9 is where
            // management accounting lives, so these mirror this university's
            // actual faculties rather than a generic school's sections.
            ['account_code' => '92', 'account_name' => 'Cost by Faculty', 'account_name_fr' => 'Coûts par Faculté', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '9'],
            ['account_code' => '921', 'account_name' => 'FAFS - Agriculture and Food Sciences', 'account_name_fr' => 'FAFS - Agriculture et Sciences Alimentaires', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '92'],
            ['account_code' => '922', 'account_name' => 'SMBS - Medical and Biomedical Sciences', 'account_name_fr' => 'SMBS - Sciences Médicales et Biomédicales', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '92'],
            ['account_code' => '923', 'account_name' => 'FMS/FBF - Management, Business and Finance', 'account_name_fr' => 'FMS/FBF - Gestion, Commerce et Finance', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '92'],
            ['account_code' => '924', 'account_name' => 'SCEDT - Computer Engineering and Digital Technology', 'account_name_fr' => 'SCEDT - Génie Informatique et Technologie Numérique', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '92'],
            ['account_code' => '925', 'account_name' => 'Central Administration', 'account_name_fr' => 'Administration Centrale', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '92'],

            ['account_code' => '93', 'account_name' => 'Revenue by Faculty', 'account_name_fr' => 'Revenus par Faculté', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '9'],
            ['account_code' => '931', 'account_name' => 'FAFS - Agriculture and Food Sciences', 'account_name_fr' => 'FAFS - Agriculture et Sciences Alimentaires', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '93'],
            ['account_code' => '932', 'account_name' => 'SMBS - Medical and Biomedical Sciences', 'account_name_fr' => 'SMBS - Sciences Médicales et Biomédicales', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '93'],
            ['account_code' => '933', 'account_name' => 'FMS/FBF - Management, Business and Finance', 'account_name_fr' => 'FMS/FBF - Gestion, Commerce et Finance', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '93'],
            ['account_code' => '934', 'account_name' => 'SCEDT - Computer Engineering and Digital Technology', 'account_name_fr' => 'SCEDT - Génie Informatique et Technologie Numérique', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '93'],
            ['account_code' => '935', 'account_name' => 'Other Income', 'account_name_fr' => 'Autres Revenus', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'credit', 'is_system' => false, 'parent_code' => '93'],
            
            ['account_code' => '94', 'account_name' => 'Project Tracking', 'account_name_fr' => 'Suivi de Projets', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'heading', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '9'],
            ['account_code' => '941', 'account_name' => 'Infrastructure Projects', 'account_name_fr' => 'Projets d\'Infrastructure', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '94'],
            ['account_code' => '942', 'account_name' => 'IT Projects', 'account_name_fr' => 'Projets Informatiques', 'class_number' => 9, 'account_type' => 'analytical', 'account_category' => 'detail', 'normal_balance' => 'debit', 'is_system' => false, 'parent_code' => '94'],
        ];
    }
}
