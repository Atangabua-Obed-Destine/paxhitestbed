<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applications now create their admission Fee at intake (before a Student row
 * exists), which means the stub StudentEnroll cannot yet reference a real
 * students.id. Follow the pattern established by
 * 2025_10_24_024155_make_enroll_fields_nullable_for_applicants and make
 * student_enrolls.student_id nullable. The FK is re-added with ON DELETE
 * CASCADE so real (non-null) rows still enforce integrity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('student_enrolls', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }
};
