<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The SYSCOHADA accounts month-end and year-end adjustments need, which the
 * installed chart did not have:
 *
 *   408  Fournisseurs, factures non parvenues   a cost incurred, invoice not yet received
 *   428  Personnel, charges à payer             staff costs owed at the period end
 *   47   Débiteurs et créditeurs divers         heading for the two below
 *   476  Charges constatées d'avance            paid now, belongs to a later period
 *   477  Produits constatés d'avance            received now, earned in a later period
 *                                               (fees paid for next academic year)
 *
 * Added only where the code is missing; nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = fn (string $code) => DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', $code)->value('id');
        $class4 = $id('4');

        if (!$class4) {
            return;
        }

        $add = function (string $code, string $fr, string $en, string $type, string $category, string $normal, ?int $parent) use ($id) {
            if ($id($code)) {
                return $id($code);
            }

            return DB::table('chart_of_accounts')->insertGetId([
                'account_code' => $code,
                'account_name' => $fr,
                'account_name_fr' => $fr,
                'description' => $en,
                'parent_id' => $parent,
                'class_number' => 4,
                'account_type' => $type,
                'account_category' => $category,
                'opening_balance' => 0,
                'current_balance' => 0,
                'normal_balance' => $normal,
                'cash_flow_category' => 'operating',
                'is_active' => 1,
                'is_system' => 0,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $add('408', 'Fournisseurs, factures non parvenues', 'Suppliers, invoices not yet received (accrued costs)', 'liability', 'detail', 'credit', $class4);
        $add('428', 'Personnel, charges a payer', 'Staff costs accrued at the period end', 'liability', 'detail', 'credit', $id('42') ?: $class4);
        $heading = $add('47', 'Debiteurs et crediteurs divers', 'Sundry debtors and creditors', 'liability', 'heading', 'credit', $class4);
        $add('476', "Charges constatees d'avance", 'Prepaid expenses', 'asset', 'detail', 'debit', $heading);
        $add('477', "Produits constates d'avance", 'Income received in advance (e.g. fees paid for next year)', 'liability', 'detail', 'credit', $heading);
    }

    public function down(): void
    {
        // Left in place: an account may carry postings by then.
    }
};
