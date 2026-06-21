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
        Schema::table('students', function (Blueprint $table) {
            $table->boolean('is_catholic_baptised')->default(0)->nullable()->after('religion');
            $table->boolean('is_confirmed')->default(0)->nullable()->after('is_catholic_baptised');
            $table->boolean('has_first_communion')->default(0)->nullable()->after('is_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['is_catholic_baptised', 'is_confirmed', 'has_first_communion']);
        });
    }
};
