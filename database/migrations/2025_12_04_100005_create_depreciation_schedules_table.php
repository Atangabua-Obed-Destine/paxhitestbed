<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Depreciation Schedule - Monthly depreciation records
     */
    public function up(): void
    {
        Schema::create('depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fixed_asset_id');
            $table->unsignedBigInteger('fiscal_year_id');
            $table->unsignedBigInteger('accounting_period_id')->nullable();
            $table->date('depreciation_date');
            $table->integer('period_number'); // 1-12 for monthly
            $table->decimal('opening_book_value', 15, 2);
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('accumulated_depreciation', 15, 2);
            $table->decimal('closing_book_value', 15, 2);
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('fixed_asset_id')->references('id')->on('fixed_assets')->onDelete('cascade');
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->onDelete('restrict');
            $table->foreign('accounting_period_id')->references('id')->on('accounting_periods')->onDelete('set null');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['fixed_asset_id', 'depreciation_date']);
            $table->index(['fiscal_year_id', 'accounting_period_id']);
            $table->index('is_posted');
            $table->unique(['fixed_asset_id', 'depreciation_date'], 'unique_asset_depreciation_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('depreciation_schedules');
    }
};
