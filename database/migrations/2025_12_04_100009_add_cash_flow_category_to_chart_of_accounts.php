<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add cash flow category to chart of accounts for cash flow statement
     */
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            // Cash flow category for proper cash flow statement classification
            $table->enum('cash_flow_category', [
                'operating',       // Operating activities
                'investing',       // Investing activities
                'financing',       // Financing activities
                'non_cash',        // Non-cash items (depreciation, etc.)
                'none'             // Not applicable
            ])->default('none')->after('normal_balance');
            
            // Sub-category for more detailed classification
            $table->string('cash_flow_subcategory')->nullable()->after('cash_flow_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropColumn(['cash_flow_category', 'cash_flow_subcategory']);
        });
    }
};
