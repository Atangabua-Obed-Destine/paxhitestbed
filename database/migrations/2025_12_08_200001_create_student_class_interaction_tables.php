<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStudentClassInteractionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Student Personal Notes for each class session
        Schema::create('student_class_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('student_enroll_id')->nullable();
            $table->text('content')->nullable();
            $table->string('title', 255)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('set null');
            
            // One note per student per class session
            $table->unique(['class_session_id', 'student_id']);
        });

        // Real-time chat messages for class sessions
        Schema::create('class_session_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('student_id')->nullable(); // null if from lecturer
            $table->unsignedBigInteger('user_id')->nullable(); // lecturer/staff user
            $table->enum('sender_type', ['student', 'lecturer', 'system'])->default('student');
            $table->text('message');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_announcement')->default(false);
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reply_to_id')->references('id')->on('class_session_messages')->onDelete('set null');
            
            $table->index(['class_session_id', 'created_at']);
        });

        // Important alerts/reminders captured by students during class
        Schema::create('class_session_alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('student_id'); // who created the alert
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->enum('alert_type', ['reminder', 'assignment', 'exam', 'important', 'deadline', 'other'])->default('important');
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->boolean('is_verified_by_lecturer')->default(false);
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->integer('upvotes')->default(0);
            $table->timestamps();

            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('verified_by_user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['class_session_id', 'alert_type']);
        });

        // Track which students upvoted an alert
        Schema::create('class_session_alert_upvotes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('alert_id');
            $table->unsignedBigInteger('student_id');
            $table->timestamps();

            $table->foreign('alert_id')->references('id')->on('class_session_alerts')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            
            $table->unique(['alert_id', 'student_id']);
        });

        // Questions submitted to lecturer during class
        Schema::create('class_session_questions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('class_session_id');
            $table->unsignedBigInteger('student_id');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->unsignedBigInteger('answered_by_user_id')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->enum('status', ['pending', 'answered', 'dismissed'])->default('pending');
            $table->boolean('is_anonymous')->default(false);
            $table->integer('upvotes')->default(0);
            $table->timestamps();

            $table->foreign('class_session_id')->references('id')->on('class_sessions')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('answered_by_user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['class_session_id', 'status']);
        });

        // Add chat_enabled field to class_sessions table
        Schema::table('class_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('class_sessions', 'chat_enabled')) {
                $table->boolean('chat_enabled')->default(true)->after('status');
            }
            if (!Schema::hasColumn('class_sessions', 'allow_questions')) {
                $table->boolean('allow_questions')->default(true)->after('chat_enabled');
            }
            if (!Schema::hasColumn('class_sessions', 'allow_alerts')) {
                $table->boolean('allow_alerts')->default(true)->after('allow_questions');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('class_session_alert_upvotes');
        Schema::dropIfExists('class_session_questions');
        Schema::dropIfExists('class_session_alerts');
        Schema::dropIfExists('class_session_messages');
        Schema::dropIfExists('student_class_notes');

        Schema::table('class_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('class_sessions', 'chat_enabled')) {
                $table->dropColumn('chat_enabled');
            }
            if (Schema::hasColumn('class_sessions', 'allow_questions')) {
                $table->dropColumn('allow_questions');
            }
            if (Schema::hasColumn('class_sessions', 'allow_alerts')) {
                $table->dropColumn('allow_alerts');
            }
        });
    }
}
