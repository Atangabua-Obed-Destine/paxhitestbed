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
        Schema::create('platform_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_enroll_id');
            $table->unsignedInteger('session_id');
            $table->decimal('fee_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('receipt_path')->nullable();
            $table->text('student_note')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->timestamps();

            $table->foreign('student_enroll_id')->references('id')->on('student_enrolls')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('sessions')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            
            // Ensure one payment record per student per session
            $table->unique(['student_enroll_id', 'session_id'], 'unique_student_session_payment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fee_payments');
    }
};
