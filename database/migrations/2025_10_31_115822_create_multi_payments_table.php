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
        // Multi payments table - stores the main payment transaction
        Schema::create('multi_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_paid', 10, 2);
            $table->string('payment_method')->nullable(); // 'online', 'manual', 'paypal', 'stripe', etc.
            $table->string('transaction_id')->nullable();
            $table->string('receipt_path')->nullable(); // for manual payments
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->date('payment_date');
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Multi payment distributions - stores how payment is distributed across fees
        Schema::create('multi_payment_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('multi_payment_id')->constrained('multi_payments')->onDelete('cascade');
            $table->foreignId('fee_id')->constrained('fees')->onDelete('cascade');
            $table->decimal('fee_amount', 10, 2); // original fee amount
            $table->decimal('amount_applied', 10, 2); // amount applied from this payment
            $table->decimal('balance_before', 10, 2); // balance before this payment
            $table->decimal('balance_after', 10, 2); // balance after this payment
            $table->enum('fee_status_after', ['pending', 'partial', 'paid']); // fee status after payment
            $table->timestamps();

            $table->index(['multi_payment_id', 'fee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multi_payment_distributions');
        Schema::dropIfExists('multi_payments');
    }
};
