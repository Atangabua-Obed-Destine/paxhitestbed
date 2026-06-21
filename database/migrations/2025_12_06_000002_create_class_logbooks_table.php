<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClassLogbooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('class_logbooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            
            // Link to class session
            $table->bigInteger('class_session_id')->unsigned();
            
            // Logbook content
            $table->string('topic_covered', 500)->nullable();
            $table->text('content_summary')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->text('teaching_methods')->nullable();
            $table->text('materials_used')->nullable();
            $table->text('assignments_given')->nullable();
            $table->text('remarks')->nullable();
            
            // Class representative (optional - selected from enrolled students)
            $table->bigInteger('class_rep_student_id')->unsigned()->nullable();
            
            // HOD info (auto-populated based on department)
            $table->bigInteger('hod_user_id')->unsigned()->nullable();
            
            // Completion status
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            
            // Tracking
            $table->bigInteger('created_by')->unsigned()->nullable();
            $table->bigInteger('updated_by')->unsigned()->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            
            // Unique constraint - one logbook per session
            $table->unique('class_session_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_logbooks');
    }
}
