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
            $table->string('present_province')->nullable()->change();
            $table->string('present_district')->nullable()->change();
            $table->string('permanent_province')->nullable()->change();
            $table->string('permanent_district')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->integer('present_province')->nullable()->change();
            $table->integer('present_district')->nullable()->change();
            $table->integer('permanent_province')->nullable()->change();
            $table->integer('permanent_district')->nullable()->change();
        });
    }
};
