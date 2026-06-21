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
            $table->integer('due_days')->nullable()->after('amount')->comment('Number of days from enrollment for due date');
            $table->decimal('fine_amount', 10, 2)->nullable()->after('due_days')->comment('Fine amount or percentage');
            $table->enum('fine_type', ['fixed', 'percentage'])->nullable()->after('fine_amount')->comment('Type of fine: fixed amount or percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_semester_fees', function (Blueprint $table) {
            $table->dropColumn(['due_days', 'fine_amount', 'fine_type']);
        });
    }
};
