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
        Schema::table('program_semester_fees', function (Blueprint $table) {
            $table->integer('due_month')->nullable()->after('amount');
            $table->integer('due_day')->nullable()->after('due_month');
            $table->dropColumn('due_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_semester_fees', function (Blueprint $table) {
            $table->integer('due_days')->nullable()->after('amount');
            $table->dropColumn('due_month');
            $table->dropColumn('due_day');
        });
    }
};
