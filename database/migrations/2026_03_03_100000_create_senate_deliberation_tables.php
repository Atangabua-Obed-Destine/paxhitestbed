<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Main senate deliberation record — one per session + semester meeting
        Schema::create('senate_deliberations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('session_id');
            $table->unsignedInteger('semester_id');
            $table->string('meeting_number')->nullable(); // e.g. "SEN/2025-2026/SEM1/001"
            $table->date('meeting_date')->nullable();
            $table->string('venue')->nullable();
            $table->string('chairperson')->nullable();
            $table->string('registrar')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'deferred'])->default('pending');
            $table->enum('overall_decision', ['pending', 'approved', 'approved_with_conditions', 'deferred', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->text('conditions')->nullable();
            $table->text('action_items')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['session_id', 'semester_id', 'meeting_number'], 'senate_session_semester_meeting');
        });

        // Per-program decision within a senate deliberation
        Schema::create('senate_deliberation_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('senate_deliberation_id');
            $table->unsignedInteger('program_id');
            $table->unsignedInteger('faculty_id')->nullable();
            $table->enum('decision', ['pending', 'approved', 'approved_with_conditions', 'deferred', 'rejected'])->default('pending');
            $table->text('remarks')->nullable();
            $table->text('conditions')->nullable();
            $table->text('action_items')->nullable();
            $table->integer('total_students')->default(0);
            $table->integer('total_passed')->default(0);
            $table->integer('total_failed')->default(0);
            $table->decimal('average_gpa', 4, 2)->default(0);
            $table->decimal('pass_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('senate_deliberation_id')->references('id')->on('senate_deliberations')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('faculty_id')->references('id')->on('faculties')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['senate_deliberation_id', 'program_id'], 'senate_program_unique');
        });

        // Academic standing classification per student per session/semester
        Schema::create('academic_standings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_enroll_id');
            $table->unsignedInteger('session_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('program_id');
            $table->unsignedInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('senate_deliberation_id')->nullable();
            $table->decimal('gpa', 4, 2)->default(0);
            $table->decimal('cgpa', 4, 2)->nullable();
            $table->integer('total_credits_registered')->default(0);
            $table->integer('total_credits_earned')->default(0);
            $table->integer('courses_registered')->default(0);
            $table->integer('courses_passed')->default(0);
            $table->integer('courses_failed')->default(0);
            $table->enum('standing', [
                'deans_list',
                'good_standing',
                'academic_warning',
                'academic_probation',
                'recommended_dismissal'
            ])->default('good_standing');
            $table->string('previous_standing')->nullable();
            $table->text('senate_remarks')->nullable();
            $table->text('conditions')->nullable();
            $table->unsignedBigInteger('classified_by')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('faculty_id')->references('id')->on('faculties')->onDelete('set null');
            $table->foreign('senate_deliberation_id')->references('id')->on('senate_deliberations')->onDelete('set null');
            $table->foreign('classified_by')->references('id')->on('users')->onDelete('set null');
            $table->unique(['student_enroll_id', 'session_id', 'semester_id'], 'standing_enroll_session_semester');
        });

        // Signatures for senate deliberation
        Schema::create('senate_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('senate_deliberation_id');
            $table->string('signatory_name');
            $table->string('signatory_position'); // e.g. "Chairperson", "Registrar", "Dean of Faculty X"
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('senate_deliberation_id')->references('id')->on('senate_deliberations')->onDelete('cascade');
        });

        // Deliberation log — audit trail of actions taken during senate meetings
        Schema::create('senate_deliberation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('senate_deliberation_id');
            $table->string('action'); // e.g. "created", "program_approved", "student_flagged", "signature_added"
            $table->string('target_type')->nullable(); // e.g. "program", "student", "signature"
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable(); // additional context data
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();

            $table->foreign('senate_deliberation_id')->references('id')->on('senate_deliberations')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senate_deliberation_logs');
        Schema::dropIfExists('senate_signatures');
        Schema::dropIfExists('academic_standings');
        Schema::dropIfExists('senate_deliberation_programs');
        Schema::dropIfExists('senate_deliberations');
    }
};
