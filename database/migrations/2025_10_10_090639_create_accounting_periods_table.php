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
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id');
            $table->string('name'); // e.g., "Janvier 2025", "Period 01"
            $table->integer('period_number'); // 1-12 for months
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->bigInteger('closed_by')->unsigned()->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('fiscal_year_id')
                  ->references('id')
                  ->on('fiscal_years')
                  ->onDelete('cascade');

            // Indexes
            $table->index('fiscal_year_id');
            $table->index('is_closed');
            $table->index(['start_date', 'end_date']);
            $table->unique(['fiscal_year_id', 'period_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
