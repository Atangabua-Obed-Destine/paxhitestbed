<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_mappings', function (Blueprint $table) {
            $table->id();
            
            // Transaction Type: fee, income, expense, payroll
            $table->enum('transaction_type', ['fee', 'income', 'expense', 'payroll']);
            
            // Transaction ID (polymorphic - references fees, incomes, expenses, or payrolls table)
            $table->unsignedBigInteger('transaction_id');
            
            // Account Mappings (Debit and Credit)
            $table->unsignedBigInteger('debit_account_id');
            $table->unsignedBigInteger('credit_account_id');
            
            // Amount (copied from transaction for quick reference)
            $table->decimal('amount', 15, 2);
            
            // Transaction Date (copied from transaction)
            $table->date('transaction_date');
            
            // Description/Notes
            $table->text('description')->nullable();
            
            // Journal Entry (auto-created when mapped)
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            
            // Mapping Details
            $table->dateTime('mapped_at');
            $table->unsignedBigInteger('mapped_by');
            
            // Status
            $table->boolean('status')->default(1);
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('debit_account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('credit_account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('mapped_by')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes
            $table->index(['transaction_type', 'transaction_id']);
            $table->index('journal_entry_id');
            $table->index('mapped_at');
            $table->index('status');
            
            // Unique constraint: one mapping per transaction
            $table->unique(['transaction_type', 'transaction_id'], 'unique_transaction_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_mappings');
    }
};
