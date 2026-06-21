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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 20)->unique(); // e.g., 521, 5211, 52111
            $table->string('account_name'); // e.g., Banques, Banque Locale
            $table->string('account_name_fr')->nullable(); // French name (Bilan)
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable(); // For hierarchical structure
            $table->tinyInteger('class_number'); // 1-8 for OHADA classes
            $table->enum('account_type', [
                'asset', 'liability', 'equity', 'revenue', 'expense', 'other'
            ]); // Account classification
            $table->enum('account_category', [
                'detail', 'heading', 'total', 'subtotal'
            ])->default('detail'); // detail = can post, heading = grouping only
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('normal_balance', ['debit', 'credit']); // Normal balance side
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // System accounts cannot be deleted
            $table->integer('display_order')->default(0);
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('account_code');
            $table->index('parent_id');
            $table->index('class_number');
            $table->index('account_type');
            $table->index('is_active');
            
            // Foreign key
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('chart_of_accounts')
                  ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
