<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of every transcript issued, with the results as they stood.
 *
 * A transcript states grades, so verifying it has to mean verifying those
 * grades. Looking the student up live would confirm only that the person
 * exists — an altered PDF would still scan as genuine, which is exactly the
 * forgery a verification code is meant to catch. So the courses, grades and
 * totals are snapshotted at the moment of issue and the QR code points at that
 * snapshot.
 *
 * A mark corrected afterwards therefore makes the old transcript verify as
 * issued, and correctly disagree with the current record. That is the point:
 * the document says what it said on the day it was signed.
 *
 * Mirrors form_a3_records, which solves the same problem for that document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transcript_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_enroll_id');
            $table->unsignedInteger('program_id')->nullable();

            $table->string('verification_code', 32)->unique();

            // What the document showed, exactly as printed.
            $table->longText('courses_snapshot')->nullable();
            $table->string('matricule')->nullable();
            $table->string('student_name')->nullable();
            $table->string('programme_name')->nullable();
            $table->decimal('cumulative_gpa', 6, 2)->default(0);
            $table->decimal('total_credits', 8, 1)->default(0);
            $table->decimal('credits_earned', 8, 1)->default(0);
            $table->unsignedInteger('total_courses')->default(0);
            $table->string('standing')->nullable();

            $table->timestamp('issued_at')->nullable();
            $table->unsignedBigInteger('issued_by')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');

            $table->index(['student_id', 'student_enroll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transcript_records');
    }
};
