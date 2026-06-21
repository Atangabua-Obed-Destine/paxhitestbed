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
        Schema::table('form_a3_records', function (Blueprint $table) {
            $table->string('verification_code', 64)->nullable()->unique()->after('dir_acad_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_a3_records', function (Blueprint $table) {
            $table->dropColumn('verification_code');
        });
    }
};
