<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier bills and the payments that settle them (accounts payable).
 *
 * Until now the school could only record an expense once it had been paid, so
 * nothing said who it owed, how much, or since when. Account 40 Fournisseurs
 * existed and nothing posted to it.
 *
 * The shape:
 *   - a bill is what a supplier invoiced: approving it posts the cost and the
 *     debt (debit the expense, credit 40);
 *   - a payment is money handed over: it posts debit 40, credit the bank or
 *     cash account, and is allocated to one or more bills;
 *   - what is still owed on a bill is always its total less its live
 *     allocations. There is deliberately no stored "paid" flag that several
 *     code paths could write and let drift (see App\Services\Accounting\
 *     SupplierPayables::owedQuery, the one definition every screen uses).
 *
 * Nothing is ever deleted once posted: a bill or a payment is voided with a
 * reversing entry, and the row stays as the audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('item_suppliers', 'tax_number')) {
                // NIU in Cameroon. Free text: formats vary.
                $table->string('tax_number', 64)->nullable()->after('address');
            }
            if (!Schema::hasColumn('item_suppliers', 'payment_terms_days')) {
                // Days from the bill date to the due date. Empty means due on
                // the bill date itself.
                $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('tax_number');
            }
            if (!Schema::hasColumn('item_suppliers', 'bank_details')) {
                $table->string('bank_details', 255)->nullable()->after('payment_terms_days');
            }
        });

        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');

            // 'bill', or 'opening_balance' for what was already owed when the
            // school started using this screen. An opening balance is paid and
            // aged like any bill, but it is last year's cost, so it is posted
            // against an equity account rather than an expense and never
            // touches this year's budget.
            $table->string('kind', 20)->default('bill');

            // Ours: SB-YYYY-NNNN.
            $table->string('number', 30)->unique();
            // Theirs: the supplier's own invoice number. Checked for
            // duplicates per supplier, so one invoice cannot be entered twice.
            $table->string('supplier_reference', 100)->nullable();

            $table->date('bill_date');
            $table->date('due_date');
            $table->string('description', 255)->nullable();
            $table->decimal('total', 15, 2)->default(0);

            // draft -> approved -> void. Draft is editable and posts nothing.
            $table->string('status', 20)->default('draft');

            $table->unsignedBigInteger('payable_account_id')->nullable();
            $table->string('attach', 255)->nullable();

            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedBigInteger('void_journal_entry_id')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('due_date');
        });

        Schema::create('supplier_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_bill_id');
            $table->unsignedSmallInteger('line_number');
            $table->string('description', 255)->nullable();

            // A bill line is filed under an expense category, which decides the
            // ledger account (its mapping) and the budget line, exactly as an
            // expense does. Null only on an opening balance.
            $table->unsignedBigInteger('expense_category_id')->nullable();

            // The account debited, fixed when the bill is approved.
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('amount', 15, 2);

            // The journal lines this bill line produced, so the budget sheet
            // can place each one on its budget line.
            $table->unsignedBigInteger('journal_entry_line_id')->nullable();
            $table->unsignedBigInteger('void_journal_entry_line_id')->nullable();

            $table->timestamps();

            $table->index('supplier_bill_id');
            $table->index('journal_entry_line_id');
            $table->index('void_journal_entry_line_id');
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            // Ours: SP-YYYY-NNNN.
            $table->string('number', 30)->unique();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);

            // App\Support\PaymentMethod keys, as expenses use.
            $table->unsignedTinyInteger('payment_method')->nullable();

            // The bank or cash account (class 5) the money left.
            $table->unsignedBigInteger('cash_account_id');
            // Optional cash box / bank tracked under Payment Accounts.
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->unsignedBigInteger('payment_account_transaction_id')->nullable();

            // Cheque or transfer number.
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('journal_entry_id')->nullable();

            $table->unsignedBigInteger('void_journal_entry_id')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_date']);
        });

        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_payment_id');
            $table->unsignedBigInteger('supplier_bill_id');
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_bill_id'], 'sp_alloc_payment_bill_unique');
            $table->index('supplier_bill_id');
        });

        // Which liability account supplier debts post to. 40 Fournisseurs, as
        // installed; the accountant can change it on the supplier bills screen.
        if (Schema::hasTable('accounting_settings')
            && !DB::table('accounting_settings')->where('setting_key', 'supplier_payable_account_id')->exists()) {
            DB::table('accounting_settings')->insert([
                'setting_key' => 'supplier_payable_account_id',
                'setting_value' => (string) (DB::table('chart_of_accounts')
                    ->whereNull('deleted_at')
                    ->where('account_code', '40')
                    ->value('id') ?? ''),
                'setting_type' => 'integer',
                'category' => 'payables',
                'description' => 'Liability account supplier bills are owed on',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bill_lines');
        Schema::dropIfExists('supplier_bills');

        Schema::table('item_suppliers', function (Blueprint $table) {
            foreach (['bank_details', 'payment_terms_days', 'tax_number'] as $column) {
                if (Schema::hasColumn('item_suppliers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        DB::table('accounting_settings')->where('setting_key', 'supplier_payable_account_id')->delete();
    }
};
