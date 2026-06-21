<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Recurring Journal Entry Lines - Line items for recurring entries
     */
    public function up(): void
    {
        Schema::create('recurring_journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recurring_journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->integer('line_number');
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->boolean('is_percentage')->default(false); // If true, amount is percentage
            $table->decimal('percentage', 5, 2)->nullable(); // Percentage of base amount
            $table->timestamps();

            // Foreign keys
            $table->foreign('recurring_journal_entry_id', 'rje_lines_rje_id_fk')->references('id')->on('recurring_journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('restrict');

            // Indexes
            $table->index('recurring_journal_entry_id', 'rje_lines_rje_id_idx');
            $table->index('account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_entry_lines');
    }
};
