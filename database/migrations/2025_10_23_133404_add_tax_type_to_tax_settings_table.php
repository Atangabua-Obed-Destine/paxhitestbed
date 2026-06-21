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
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->tinyInteger('tax_type')->after('title')->default(1)->comment('1=Percentage, 2=Fixed Amount');
            $table->double('fixed_amount', 10, 2)->after('percentange')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropColumn(['tax_type', 'fixed_amount']);
        });
    }
};
