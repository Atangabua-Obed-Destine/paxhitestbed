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
        Schema::table('fees_categories', function (Blueprint $table) {
            $table->boolean('is_admission')->default(0)->after('is_resit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fees_categories', function (Blueprint $table) {
            $table->dropColumn('is_admission');
        });
    }
};
