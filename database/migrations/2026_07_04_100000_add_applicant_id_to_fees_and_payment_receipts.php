<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admission fees and their receipts predate the Student record — the applicant
 * hasn't been enrolled yet. Historically the code worked around this by creating
 * a "stub" StudentEnroll with student_id = NULL, and by (incorrectly) stuffing
 * the application id into payment_receipts.student_id. This migration gives
 * both tables a first-class applicant_id column so that lifecycle is
 * representable in the data model, not just in convention.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->unsignedBigInteger('applicant_id')->nullable()->after('student_enroll_id');
            $table->foreign('applicant_id')->references('id')->on('applications')->nullOnDelete();
            $table->index('applicant_id');
        });

        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('applicant_id')->nullable()->after('student_id');
            $table->foreign('applicant_id')->references('id')->on('applications')->nullOnDelete();
            $table->index('applicant_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
            $table->dropIndex(['applicant_id']);
            $table->dropColumn('applicant_id');
        });

        Schema::table('fees', function (Blueprint $table) {
            $table->dropForeign(['applicant_id']);
            $table->dropIndex(['applicant_id']);
            $table->dropColumn('applicant_id');
        });
    }
};
