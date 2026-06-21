<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmployerContributionToTaxSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            // Whether this tax is shared between employee and employer
            $table->boolean('is_shared')->default(false)->after('tax_type');
            
            // Employer's contribution percentage (if percentage-based)
            $table->decimal('employer_percentage', 8, 4)->default(0)->after('percentange');
            
            // Employer's fixed amount contribution (if fixed amount-based)
            $table->decimal('employer_fixed_amount', 15, 2)->default(0)->after('fixed_amount');
            
            // Who pays: 'employee', 'employer', 'both'
            $table->enum('paid_by', ['employee', 'employer', 'both'])->default('employee')->after('is_shared');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropColumn(['is_shared', 'paid_by', 'employer_percentage', 'employer_fixed_amount']);
        });
    }
}
