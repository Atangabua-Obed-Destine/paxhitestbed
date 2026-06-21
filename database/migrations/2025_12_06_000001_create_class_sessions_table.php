<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClassSessionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Link to scheduled class (NULL if extra/unscheduled class)
            $table->bigInteger('class_routine_id')->unsigned()->nullable();
            
            // Teacher info
            $table->bigInteger('teacher_id')->unsigned();
            
            // Class details
            $table->bigInteger('subject_id')->unsigned();
            $table->integer('program_id')->unsigned();
            $table->integer('session_id')->unsigned();
            $table->integer('semester_id')->unsigned();
            $table->integer('section_id')->unsigned()->nullable();
            
            // Date and scheduled times
            $table->date('date');
            $table->time('scheduled_start_time');
            $table->time('scheduled_end_time');
            
            // Actual times (recorded from student clock in/out)
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->integer('scheduled_duration_minutes')->nullable();
            
            // Status: pending, in_progress, completed, incomplete, cancelled
            $table->string('status', 20)->default('pending');
            
            // Is this a scheduled class or extra class?
            $table->boolean('is_scheduled')->default(true);
            
            // Attendance validation
            $table->boolean('meets_minimum_duration')->nullable();
            $table->decimal('duration_percentage', 5, 2)->nullable();
            
            // Lecturer attendance status (1=Present, 2=Absent based on duration validation)
            $table->tinyInteger('lecturer_attendance_status')->nullable();
            
            // Tracking
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
            
            // Indexes for faster queries
            $table->index(['teacher_id', 'date']);
            $table->index(['subject_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_sessions');
    }
}
