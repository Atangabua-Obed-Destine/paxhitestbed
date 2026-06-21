<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddEmployerContributionToPayrollsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Employer's tax/contribution amount (e.g., employer's share of CNPS)
            $table->decimal('employer_tax', 15, 2)->default(0)->after('tax');
            
            // Total labor cost (net_salary + employer_tax) - what the company actually pays
            $table->decimal('total_cost', 15, 2)->default(0)->after('net_salary');
        });
        
        // Update existing records to set total_cost
        DB::statement('UPDATE payrolls SET total_cost = net_salary + employer_tax WHERE total_cost = 0');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['employer_tax', 'total_cost']);
        });
    }
}
