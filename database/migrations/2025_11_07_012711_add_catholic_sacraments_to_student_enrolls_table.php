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
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->unsignedBigInteger('religion')->nullable();
            $table->boolean('is_catholic_baptised')->default(false);
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('has_first_communion')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropColumn(['religion', 'is_catholic_baptised', 'is_confirmed', 'has_first_communion']);
        });
    }
};
