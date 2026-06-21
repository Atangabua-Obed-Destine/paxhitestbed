<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Year-End Closing Records - Track fiscal year closing process
     */
    public function up(): void
    {
        Schema::create('year_end_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'reversed'])->default('pending');
            $table->date('closing_date');
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_expenses', 15, 2)->default(0);
            $table->decimal('net_income', 15, 2)->default(0); // revenue - expenses
            $table->unsignedBigInteger('retained_earnings_account_id')->nullable(); // Account to transfer net income
            $table->unsignedBigInteger('income_summary_account_id')->nullable(); // Temporary account for closing
            $table->unsignedBigInteger('closing_entry_id')->nullable(); // Journal entry closing revenues/expenses
            $table->unsignedBigInteger('transfer_entry_id')->nullable(); // Journal entry transferring to retained earnings
            $table->unsignedBigInteger('opening_entry_id')->nullable(); // Opening balances for new year
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('cascade');
            $table->foreign('retained_earnings_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('income_summary_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('closing_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('transfer_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('opening_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reversed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('fiscal_year_id');
            $table->index('status');
            $table->unique('fiscal_year_id', 'unique_fiscal_year_closing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('year_end_closings');
    }
};
