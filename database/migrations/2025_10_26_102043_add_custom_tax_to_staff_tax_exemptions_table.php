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
        Schema::table('staff_tax_exemptions', function (Blueprint $table) {
            $table->decimal('custom_percentage', 10, 2)->nullable()->after('reason');
            $table->decimal('custom_fixed_amount', 20, 2)->nullable()->after('custom_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_tax_exemptions', function (Blueprint $table) {
            $table->dropColumn(['custom_percentage', 'custom_fixed_amount']);
        });
    }
};
