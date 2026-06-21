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
        Schema::create('payment_plan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained('payment_plan_installments')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->integer('payment_method')->comment('1=Card, 2=Cash, 3=Cheque, 4=Bank, 5=E-wallet, 6=Manual, 7=Online');
            $table->date('payment_date');
            $table->string('reference_no')->nullable();
            $table->string('receipt_path')->nullable();
            $table->morphs('paid_by'); // Can be User or Student
            $table->text('note')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('installment_id');
            $table->index('payment_date');
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plan_payments');
    }
};
