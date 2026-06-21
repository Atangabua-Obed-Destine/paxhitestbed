<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentClassAttendancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('student_class_attendances', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Link to class session
            $table->bigInteger('class_session_id')->unsigned();
            
            // Student reference
            $table->bigInteger('student_enroll_id')->unsigned();
            $table->string('matricule', 50); // Store for quick reference
            
            // Clock in/out tracking
            $table->timestamp('clock_in_time')->nullable();
            $table->timestamp('clock_out_time')->nullable();
            
            // Duration tracking (in minutes)
            $table->integer('duration_minutes')->nullable();
            
            // Attendance status
            // P = Present, A = Absent, L = Late, E = Early Leave, I = Incomplete (clocked in but not out)
            $table->enum('status', ['P', 'A', 'L', 'E', 'I'])->default('P');
            
            // Late tracking
            $table->boolean('is_late')->default(false);
            $table->integer('late_minutes')->default(0);
            
            // Flags
            $table->boolean('has_clocked_out')->default(false);
            $table->boolean('is_first_clock_out')->default(false); // Was this the first student to clock out?
            
            // Device/scan info
            $table->string('clock_in_device', 100)->nullable();
            $table->string('clock_out_device', 100)->nullable();
            $table->string('clock_in_ip', 45)->nullable();
            $table->string('clock_out_ip', 45)->nullable();
            
            // Last scan time (for cooldown tracking)
            $table->timestamp('last_scan_time')->nullable();
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
            
            // Unique constraint - one record per student per session
            $table->unique(['class_session_id', 'student_enroll_id'], 'unique_student_class_session');
            
            // Indexes for performance
            $table->index('matricule');
            $table->index('status');
            $table->index(['class_session_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('student_class_attendances');
    }
}
