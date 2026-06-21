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
        Schema::create('default_account_mappings', function (Blueprint $table) {
            $table->id();
            
            // Mapping Type: fee_category, income_category, expense_category, payroll
            $table->enum('mapping_type', ['fee_category', 'income_category', 'expense_category', 'payroll']);
            
            // Category/Type ID (nullable for payroll which doesn't have categories)
            $table->unsignedBigInteger('category_id')->nullable();
            
            // Account Mappings (Debit and Credit)
            $table->unsignedBigInteger('debit_account_id');
            $table->unsignedBigInteger('credit_account_id');
            
            // Description/Notes
            $table->text('description')->nullable();
            
            // Status
            $table->boolean('status')->default(1);
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('debit_account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('credit_account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index(['mapping_type', 'category_id']);
            $table->index('status');
            
            // Unique constraint: one mapping per type+category combination
            $table->unique(['mapping_type', 'category_id'], 'unique_mapping_type_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_account_mappings');
    }
};
