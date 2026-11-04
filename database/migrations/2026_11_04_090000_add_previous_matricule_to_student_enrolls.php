<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remember the matricule a programme change retired.
 *
 * A matricule is numbered within its faculty's series — PAX25MFH055 is the
 * fifty-fifth in the FBMS series — and the generator hands out max + 1 in
 * that series. Moving within a faculty is safe: the new number is max + 1 of
 * the same series, so the old one sits below the max and is never issued
 * again.
 *
 * Moving ACROSS faculties is not. The old number leaves its series, the
 * series' max drops back by one, and the next admission into that faculty is
 * given the retired number — one that is already printed on the moved
 * student's admission-fee receipt. On this database PAX25MFH055 is the
 * highest FBMS number and belongs to a student the screen would let move.
 *
 * So the retired number is kept here, and Student::generateEnrollmentMatricule
 * counts it as taken. The column is also the only record of what the student
 * used to be called, which a receipt query will one day need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->string('previous_matricule', 50)->nullable()->after('matricule');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropColumn('previous_matricule');
        });
    }
};
