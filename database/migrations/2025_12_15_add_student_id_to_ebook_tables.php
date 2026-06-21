<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add student_id to e-book related tables for student portal support
     */
    public function up(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Add student_id to e_book_readings (if not exists)
        if (!Schema::hasColumn('e_book_readings', 'student_id')) {
            Schema::table('e_book_readings', function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('user_id');
                $table->index('student_id');
            });
        }
        
        // Add foreign key if not exists
        try {
            Schema::table('e_book_readings', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        } catch (\Exception $e) {
            // Foreign key may already exist
        }

        // Add student_id to e_book_favorites (if not exists)
        if (!Schema::hasColumn('e_book_favorites', 'student_id')) {
            Schema::table('e_book_favorites', function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('user_id');
                $table->index('student_id');
            });
            
            Schema::table('e_book_favorites', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        }

        // Add student_id to e_book_reviews (if not exists)
        if (!Schema::hasColumn('e_book_reviews', 'student_id')) {
            Schema::table('e_book_reviews', function (Blueprint $table) {
                $table->unsignedBigInteger('student_id')->nullable()->after('user_id');
                $table->index('student_id');
            });
            
            Schema::table('e_book_reviews', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        if (Schema::hasColumn('e_book_readings', 'student_id')) {
            Schema::table('e_book_readings', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->dropIndex(['student_id']);
                $table->dropColumn('student_id');
            });
        }

        if (Schema::hasColumn('e_book_favorites', 'student_id')) {
            Schema::table('e_book_favorites', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->dropIndex(['student_id']);
                $table->dropColumn('student_id');
            });
        }

        if (Schema::hasColumn('e_book_reviews', 'student_id')) {
            Schema::table('e_book_reviews', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
                $table->dropIndex(['student_id']);
                $table->dropColumn('student_id');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
