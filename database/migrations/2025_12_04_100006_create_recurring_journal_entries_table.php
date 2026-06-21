<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Recurring Journal Entries - Template for recurring entries
     */
    public function up(): void
    {
        Schema::create('recurring_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Template name
            $table->text('description')->nullable();
            $table->enum('journal_type', ['general', 'sales', 'purchase', 'cash', 'bank', 'adjustment'])->default('general');
            $table->enum('frequency', ['daily', 'weekly', 'bi_weekly', 'monthly', 'quarterly', 'semi_annually', 'annually'])->default('monthly');
            $table->integer('day_of_month')->nullable(); // For monthly (1-31)
            $table->integer('day_of_week')->nullable(); // For weekly (0=Sunday, 6=Saturday)
            $table->integer('month_of_year')->nullable(); // For annually (1-12)
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null = no end
            $table->date('next_run_date');
            $table->date('last_run_date')->nullable();
            $table->integer('occurrences')->nullable(); // Null = unlimited
            $table->integer('occurrences_completed')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_post')->default(false); // Auto-post when generated
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('is_active');
            $table->index('next_run_date');
            $table->index('frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_journal_entries');
    }
};
