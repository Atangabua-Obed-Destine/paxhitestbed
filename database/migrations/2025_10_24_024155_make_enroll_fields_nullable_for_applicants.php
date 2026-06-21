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
            // Drop foreign keys first
            $table->dropForeign(['session_id']);
            $table->dropForeign(['semester_id']);
            $table->dropForeign(['section_id']);
            
            // Make columns nullable
            $table->integer('session_id')->unsigned()->nullable()->change();
            $table->integer('semester_id')->unsigned()->nullable()->change();
            $table->integer('section_id')->unsigned()->nullable()->change();
            
            // Re-add foreign keys with nullable constraint
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['session_id']);
            $table->dropForeign(['semester_id']);
            $table->dropForeign(['section_id']);
            
            // Make columns NOT nullable again
            $table->integer('session_id')->unsigned()->nullable(false)->change();
            $table->integer('semester_id')->unsigned()->nullable(false)->change();
            $table->integer('section_id')->unsigned()->nullable(false)->change();
            
            // Re-add foreign keys
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
        });
    }
};
