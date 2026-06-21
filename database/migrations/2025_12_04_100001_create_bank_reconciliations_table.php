<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bank Reconciliation Module - Tracks bank statement reconciliation
     */
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_account_id'); // The bank/cash account being reconciled
            $table->unsignedBigInteger('chart_of_account_id')->nullable(); // Link to COA if available
            $table->date('statement_date'); // Bank statement date
            $table->date('reconciliation_date'); // When reconciliation was performed
            $table->string('statement_reference')->nullable(); // Bank statement reference number
            $table->decimal('statement_opening_balance', 15, 2)->default(0);
            $table->decimal('statement_closing_balance', 15, 2); // Balance per bank statement
            $table->decimal('book_balance', 15, 2); // Balance per books
            $table->decimal('adjusted_book_balance', 15, 2)->nullable(); // After adjustments
            $table->decimal('outstanding_deposits', 15, 2)->default(0); // Deposits in transit
            $table->decimal('outstanding_checks', 15, 2)->default(0); // Outstanding checks
            $table->decimal('bank_charges', 15, 2)->default(0); // Bank charges not recorded
            $table->decimal('bank_interest', 15, 2)->default(0); // Interest earned not recorded
            $table->decimal('other_adjustments', 15, 2)->default(0);
            $table->decimal('difference', 15, 2)->default(0); // Should be 0 when reconciled
            $table->enum('status', ['draft', 'in_progress', 'completed', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('payment_account_id')->references('id')->on('payment_accounts')->onDelete('cascade');
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('reconciled_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['payment_account_id', 'statement_date']);
            $table->index('status');
            $table->unique(['payment_account_id', 'statement_date'], 'unique_account_statement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
