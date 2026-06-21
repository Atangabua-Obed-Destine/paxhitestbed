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
        Schema::create('student_credits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->decimal('original_amount', 12, 2)->comment('Original credit amount');
            $table->decimal('remaining_amount', 12, 2)->comment('Remaining credit available');
            $table->unsignedBigInteger('source_fee_id')->nullable()->comment('Fee that generated the overpayment');
            $table->enum('source_type', ['overpayment', 'refund_reversal', 'admin_adjustment', 'transfer'])->default('overpayment');
            $table->enum('status', ['available', 'partially_applied', 'fully_applied', 'refunded', 'expired'])->default('available');
            $table->text('note')->nullable();
            
            // Refund tracking fields
            $table->boolean('refund_requested')->default(false);
            $table->datetime('refund_requested_at')->nullable();
            $table->unsignedBigInteger('refund_requested_by')->nullable();
            $table->boolean('refund_approved')->default(false);
            $table->datetime('refund_approved_at')->nullable();
            $table->unsignedBigInteger('refund_approved_by')->nullable();
            $table->datetime('refund_processed_at')->nullable();
            $table->unsignedBigInteger('refund_processed_by')->nullable();
            $table->enum('refund_method', ['cash', 'bank_transfer', 'cheque', 'mobile_money'])->nullable();
            $table->string('refund_reference')->nullable()->comment('Bank reference, cheque number, etc.');
            $table->text('refund_note')->nullable();
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('source_fee_id')->references('id')->on('fees')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('refund_requested_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('refund_approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('refund_processed_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['student_id', 'status']);
            $table->index(['status', 'remaining_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_credits');
    }
};
