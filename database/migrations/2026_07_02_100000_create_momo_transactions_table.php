<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('momo_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->index(); // mtn | orange
            $table->string('environment', 20); // sandbox | production
            $table->unsignedBigInteger('fee_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->nullable()->index();
            $table->unsignedBigInteger('payment_receipt_id')->nullable()->index();
            $table->string('initiated_by_type', 30)->nullable(); // applicant | student | web
            $table->unsignedBigInteger('initiated_by_id')->nullable();
            $table->string('reference_id', 100)->unique(); // X-Reference-Id (UUIDv4)
            $table->string('external_id', 100)->nullable();
            $table->string('msisdn', 25);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8);
            $table->string('status', 30)->default('pending')->index(); // pending | successful | failed | timeout
            $table->string('financial_transaction_id', 100)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->json('raw_request')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_transactions');
    }
};
