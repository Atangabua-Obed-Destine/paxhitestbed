<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How was this payment settled — by a person, or by the gateway?
 *
 * The status enum (pending/approved/rejected) says where a payment stands, and
 * that is still the only thing access depends on. It does not say who decided,
 * and the screens were built assuming the answer was always "an admin, looking
 * at an uploaded screenshot": the verification list renders receipt_path as a
 * link, and offers Approve and Reject buttons.
 *
 * A gateway payment has no screenshot and is not a human's to approve. Without
 * a column saying so, those rows would show a broken image link and two buttons
 * that must never be pressed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_fee_payments', function (Blueprint $table) {
            $table->string('paid_via', 20)->default('manual')->after('paid_amount')
                ->comment('manual | fapshi');
            $table->string('gateway_trans_id', 100)->nullable()->after('paid_via')
                ->comment("The provider's own transaction id, for tracing a payment back");
        });
    }

    public function down(): void
    {
        Schema::table('platform_fee_payments', function (Blueprint $table) {
            $table->dropColumn(['paid_via', 'gateway_trans_id']);
        });
    }
};
