<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resit_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_enroll_id');
            $table->unsignedBigInteger('subject_id');
            $table->unsignedInteger('session_id');
            $table->unsignedInteger('resit_session_id')->nullable();
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('workflow_state')->default('requested');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedBigInteger('state_changed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('resit_session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('state_changed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resit_requests');
    }
};
