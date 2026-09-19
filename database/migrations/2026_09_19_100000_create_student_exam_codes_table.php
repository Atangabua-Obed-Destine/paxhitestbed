<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national exam code CNOENC issues each student for the HND exam.
 *
 * One row per sitting rather than one per student: HND runs two years, so a
 * student is registered again at Level 2 with a new code, and each year's list
 * has to stay printable exactly as it was sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_exam_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('code', 40);
            // The programme as it was when the code was issued, so an old list
            // reprints unchanged even if the student later moves programme.
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // One code per student per sitting.
            $table->unique(['student_id', 'session_id', 'level'], 'student_exam_codes_sitting_unique');
            // The commission never issues the same code twice; this catches a
            // code typed onto the wrong student.
            $table->unique('code', 'student_exam_codes_code_unique');
            $table->index(['session_id', 'level'], 'student_exam_codes_sitting_index');
            $table->index('program_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_exam_codes');
    }
};
