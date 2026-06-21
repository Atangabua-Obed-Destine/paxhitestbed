<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table tracks the publishing workflow state for each subject per 
     * program/session/semester/section/exam_type combination.
     * It enables bulk publishing across all courses for a given selection.
     */
    public function up(): void
    {
        Schema::create('exam_publishing_states', function (Blueprint $table) {
            $table->id();
            
            // Selection criteria - what cohort/context this state belongs to
            // Note: programs, sessions, semesters, sections, exam_types use int(10) unsigned
            // subjects and users use bigint(20) unsigned
            $table->unsignedInteger('program_id');
            $table->unsignedInteger('session_id');
            $table->unsignedInteger('semester_id');
            $table->unsignedInteger('section_id')->nullable(); // null = all sections
            $table->unsignedBigInteger('subject_id'); // subjects uses bigint
            $table->unsignedInteger('exam_type_id');
            
            // Workflow state (draft, submitted, checked, approved, published)
            $table->string('workflow_state', 50)->default('draft');
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedBigInteger('state_changed_by')->nullable(); // users uses bigint
            
            // Review tracking
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable(); // users uses bigint
            $table->text('review_notes')->nullable();
            
            // Publishing tracking  
            $table->date('publish_date')->nullable();
            $table->time('publish_time')->nullable();
            $table->unsignedBigInteger('published_by')->nullable(); // users uses bigint
            $table->timestamp('published_at')->nullable();
            
            // Statistics snapshot at time of publishing
            $table->integer('total_students')->default(0);
            $table->integer('students_with_marks')->default(0);
            $table->integer('students_passed')->default(0);
            $table->integer('students_failed')->default(0);
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable(); // users uses bigint
            $table->unsignedBigInteger('updated_by')->nullable(); // users uses bigint
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('exam_type_id')->references('id')->on('exam_types')->onDelete('cascade');
            $table->foreign('state_changed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Unique constraint - only one state per combination
            $table->unique([
                'program_id', 
                'session_id', 
                'semester_id', 
                'section_id', 
                'subject_id', 
                'exam_type_id'
            ], 'exam_pub_unique_combination');
            
            // Indexes for common queries
            $table->index(['program_id', 'session_id', 'semester_id', 'exam_type_id'], 'exam_pub_filter_idx');
            $table->index('workflow_state', 'exam_pub_state_idx');
        });
        
        // Create workflow log table for audit trail
        Schema::create('exam_publishing_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_publishing_state_id');
            $table->string('from_state', 50)->nullable();
            $table->string('to_state', 50);
            $table->unsignedBigInteger('changed_by')->nullable(); // users uses bigint
            $table->text('notes')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
            
            $table->foreign('exam_publishing_state_id')
                ->references('id')
                ->on('exam_publishing_states')
                ->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('exam_publishing_state_id', 'exam_pub_log_state_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_publishing_workflow_logs');
        Schema::dropIfExists('exam_publishing_states');
    }
};
