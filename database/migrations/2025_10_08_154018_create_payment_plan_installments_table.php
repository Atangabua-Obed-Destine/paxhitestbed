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
        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_plan_id')->constrained('payment_plans')->onDelete('cascade');
            $table->integer('installment_number')->comment('1, 2, 3...');
            $table->decimal('amount', 10, 2)->comment('Installment amount');
            $table->date('due_date');
            $table->decimal('paid_amount', 10, 2)->default(0.00)->comment('Amount paid for this installment');
            $table->decimal('late_fee', 10, 2)->default(0.00)->comment('Late fee applied');
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue'])->default('pending');
            $table->date('grace_period_ends')->nullable()->comment('Grace period end date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('payment_plan_id');
            $table->index('due_date');
            $table->index('status');
            $table->index(['payment_plan_id', 'status']);
            $table->unique(['payment_plan_id', 'installment_number'], 'pp_installments_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
    }
};
