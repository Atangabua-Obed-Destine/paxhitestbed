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
            // Add matricule field after student_id
            // This will store enrollment-specific matricules (e.g., PAX25BF001A for undergrad, PAX25MF001 for masters)
            $table->string('matricule', 50)->nullable()->after('student_id');
            
            // Add index for faster lookups
            $table->index('matricule');
        });
        
        // Backfill matricule for existing enrollments
        // Copy student_id from students table to the FIRST enrollment only
        // Later enrollments (postgraduate) will get new matricules
        DB::statement("
            UPDATE student_enrolls se
            INNER JOIN students s ON se.student_id = s.id
            INNER JOIN (
                SELECT student_id, MIN(id) as first_enroll_id
                FROM student_enrolls
                GROUP BY student_id
            ) first_enroll ON se.id = first_enroll.first_enroll_id
            SET se.matricule = s.student_id
            WHERE se.matricule IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropIndex(['matricule']);
            $table->dropColumn('matricule');
        });
    }
};
