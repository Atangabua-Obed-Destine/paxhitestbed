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
        Schema::table('applications', function (Blueprint $table) {
            $table->string('present_province', 191)->nullable()->change();
            $table->string('present_district', 191)->nullable()->change();
            $table->string('permanent_province', 191)->nullable()->change();
            $table->string('permanent_district', 191)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->integer('present_province')->unsigned()->nullable()->change();
            $table->integer('present_district')->unsigned()->nullable()->change();
            $table->integer('permanent_province')->unsigned()->nullable()->change();
            $table->integer('permanent_district')->unsigned()->nullable()->change();
        });
    }
};
