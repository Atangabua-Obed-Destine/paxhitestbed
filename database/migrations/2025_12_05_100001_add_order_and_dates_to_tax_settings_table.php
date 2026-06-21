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
            $table->integer('bracket_order')->after('title')->default(0)->comment('Order for progressive tax calculation');
            $table->date('effective_from')->after('status')->nullable()->comment('Date when this bracket becomes effective');
            $table->date('effective_to')->after('effective_from')->nullable()->comment('Date when this bracket expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_settings', function (Blueprint $table) {
            $table->dropColumn(['bracket_order', 'effective_from', 'effective_to']);
        });
    }
};
