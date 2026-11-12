<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A student exemption means "this student, for this academic session".
 *
 * It was recorded against one enrolment row, which had two consequences. The
 * form could only offer the enrolments that already existed, so a student
 * could not be excused for 2026/2027 until they had been enrolled for it —
 * the Bursar's actual complaint. And a student with several enrolments in a
 * session (semester, resit, semester, resit) was listed once per enrolment.
 *
 * The exemption now carries the student and the session directly. The
 * enrolment it was once granted against is kept on old rows as history; the
 * access check no longer reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_exemptions', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_fee_exemptions', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->after('student_enroll_id')
                    ->comment('If exemption_type=student: the student excused');
                $table->index(['student_id', 'session_id']);
            }
        });

        // Carry the exemptions already granted across: the student and the
        // session come from the enrolment each one was recorded against.
        DB::statement('
            UPDATE platform_fee_exemptions e
            JOIN student_enrolls se ON se.id = e.student_enroll_id
            SET e.student_id = se.student_id,
                e.session_id = COALESCE(e.session_id, se.session_id)
            WHERE e.exemption_type = "student" AND e.student_id IS NULL
        ');

        // The enrolment reference is history now. Deleting an enrolment must
        // not delete an exemption the Bursar granted to the student.
        Schema::table('platform_fee_exemptions', function (Blueprint $table) {
            $table->dropForeign(['student_enroll_id']);
        });
        Schema::table('platform_fee_exemptions', function (Blueprint $table) {
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('set null');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_exemptions', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['student_enroll_id']);
        });
        Schema::table('platform_fee_exemptions', function (Blueprint $table) {
            $table->dropIndex(['student_id', 'session_id']);
            $table->dropColumn('student_id');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
        });
    }
};
