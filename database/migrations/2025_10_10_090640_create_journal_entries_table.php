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
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number')->unique(); // e.g., JE-2025-0001
            $table->date('entry_date');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->enum('journal_type', [
                'general', 'sales', 'purchase', 'cash', 'bank', 'adjustment', 'opening', 'closing'
            ])->default('general');
            $table->text('description');
            $table->string('reference_number')->nullable(); // External reference (invoice, receipt)
            $table->string('reference_type')->nullable(); // fee, expense, income, payroll, etc
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of the referenced record
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->boolean('is_posted')->default(false);
            $table->boolean('is_system_generated')->default(false); // Auto-generated from transactions
            $table->bigInteger('posted_by')->unsigned()->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->unsignedBigInteger('reversed_entry_id')->nullable(); // Link to reversing entry
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('fiscal_year_id')
                  ->references('id')
                  ->on('fiscal_years')
                  ->onDelete('restrict');
            
            $table->foreign('accounting_period_id')
                  ->references('id')
                  ->on('accounting_periods')
                  ->onDelete('restrict');

            // Indexes
            $table->index('entry_number');
            $table->index('entry_date');
            $table->index('fiscal_year_id');
            $table->index('accounting_period_id');
            $table->index('is_posted');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
