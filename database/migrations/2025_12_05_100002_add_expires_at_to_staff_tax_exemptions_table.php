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
            $table->date('expires_at')->after('custom_fixed_amount')->nullable()->comment('Date when exemption expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_tax_exemptions', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
