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
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fee_id'); // Related fee record
            $table->unsignedBigInteger('student_id'); // Student who uploaded
            $table->string('receipt_file'); // Path to uploaded receipt
            $table->string('payment_reference')->nullable(); // Transaction/reference number
            $table->date('payment_date'); // When student made the payment
            $table->double('amount', 10, 2); // Amount paid
            $table->integer('payment_method')->nullable(); // Payment method used (1-6)
            $table->text('student_note')->nullable(); // Student's note/description
            $table->enum('verification_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('verification_note')->nullable(); // Admin's feedback
            $table->unsignedBigInteger('verified_by')->nullable(); // Admin who verified
            $table->timestamp('verified_at')->nullable(); // When verified
            $table->timestamps();

            // Foreign keys
            $table->foreign('fee_id')->references('id')->on('fees')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance
            $table->index('verification_status');
            $table->index('payment_date');
            $table->index(['fee_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
