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
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->integer('line_number'); // Order of lines in the entry
            $table->text('description')->nullable();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('journal_entry_id')
                  ->references('id')
                  ->on('journal_entries')
                  ->onDelete('cascade');
            
            $table->foreign('account_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->onDelete('restrict');

            // Indexes
            $table->index('journal_entry_id');
            $table->index('account_id');
            $table->index(['journal_entry_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
