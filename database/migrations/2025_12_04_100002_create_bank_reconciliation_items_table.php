<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bank Reconciliation Items - Individual transaction matching
     */
    public function up(): void
    {
        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_id');
            $table->enum('item_type', [
                'outstanding_check',      // Checks issued but not yet cleared
                'deposit_in_transit',     // Deposits made but not yet credited
                'bank_charge',            // Bank charges not in books
                'bank_interest',          // Interest earned not in books
                'nsf_check',              // Non-sufficient funds (bounced checks)
                'direct_deposit',         // Direct deposits not in books
                'direct_debit',           // Direct debits not in books
                'error_correction',       // Corrections for errors
                'other_adjustment'        // Other adjustments
            ]);
            $table->string('reference_number')->nullable(); // Check number, transaction ref
            $table->date('transaction_date');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->enum('effect', ['add', 'subtract']); // Effect on book balance
            $table->boolean('is_cleared')->default(false); // Marked as cleared in next period
            $table->date('cleared_date')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable(); // Link to adjusting entry
            $table->unsignedBigInteger('payment_account_transaction_id')->nullable(); // Link to original transaction
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('bank_reconciliation_id')->references('id')->on('bank_reconciliations')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('payment_account_transaction_id')->references('id')->on('payment_account_transactions')->onDelete('set null');

            // Indexes
            $table->index('bank_reconciliation_id');
            $table->index('item_type');
            $table->index('is_cleared');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_items');
    }
};
