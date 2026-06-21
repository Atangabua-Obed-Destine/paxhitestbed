<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fixed Assets Module - Track school assets and depreciation
     */
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_fr')->nullable();
            $table->text('description')->nullable();
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production', 'none'])->default('straight_line');
            $table->integer('useful_life_years')->default(5); // Default useful life
            $table->decimal('salvage_value_percent', 5, 2)->default(0); // Percentage of cost as salvage value
            $table->unsignedBigInteger('asset_account_id')->nullable(); // COA account for assets
            $table->unsignedBigInteger('depreciation_account_id')->nullable(); // COA expense account
            $table->unsignedBigInteger('accumulated_depreciation_account_id')->nullable(); // COA contra-asset account
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with short names to avoid MySQL identifier length limit
            $table->foreign('asset_account_id', 'fac_asset_acct_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('depreciation_account_id', 'fac_depr_acct_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('accumulated_depreciation_account_id', 'fac_accum_depr_acct_fk')->references('id')->on('chart_of_accounts')->onDelete('set null');
            $table->foreign('created_by', 'fac_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'fac_updated_by_fk')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
