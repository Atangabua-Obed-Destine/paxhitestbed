<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fixed Assets - Individual asset records
     */
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code')->unique(); // Unique identifier like FA-2024-001
            $table->string('name');
            $table->string('name_fr')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('serial_number')->nullable();
            $table->string('model')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('location')->nullable(); // Where the asset is located
            $table->unsignedInteger('department_id')->nullable(); // Which department owns it
            $table->unsignedBigInteger('custodian_id')->nullable(); // Staff responsible for it
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->integer('useful_life_months');
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production', 'none'])->default('straight_line');
            $table->decimal('declining_balance_rate', 5, 2)->nullable(); // For declining balance method
            $table->date('depreciation_start_date');
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2); // acquisition_cost - accumulated_depreciation
            $table->date('last_depreciation_date')->nullable();
            $table->enum('status', ['active', 'fully_depreciated', 'disposed', 'under_maintenance', 'lost'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->string('disposal_reason')->nullable();
            $table->unsignedBigInteger('disposal_journal_entry_id')->nullable();
            $table->string('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('purchase_journal_entry_id')->nullable(); // Initial purchase entry
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('category_id')->references('id')->on('fixed_asset_categories')->onDelete('restrict');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('custodian_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('disposal_journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('purchase_journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('asset_code');
            $table->index('category_id');
            $table->index('status');
            $table->index('acquisition_date');
            $table->index('depreciation_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
