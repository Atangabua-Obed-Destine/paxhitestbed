<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let an instalment payment be reversed instead of deleted.
 *
 * Nothing could undo a payment plan instalment payment: the money stayed on the
 * instalment and on the fee whatever had gone wrong. A fee payment has had a
 * Reverse for a while (PaymentReversalService), and it keeps the receipt with
 * who reversed it and why rather than removing the history. An instalment
 * payment gets the same treatment, so these columns mirror the ones on
 * payment_receipts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('note');
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'reversed_at', 'reversed_by', 'reversal_reason']);
        });
    }
};
