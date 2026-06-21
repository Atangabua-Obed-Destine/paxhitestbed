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
        Schema::table('result_contributions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->after('id');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            
            // Add composite unique for subject_id and status
            $table->unique(['subject_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_contributions', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropUnique(['subject_id', 'status']);
            $table->dropColumn('subject_id');
        });
    }
};
