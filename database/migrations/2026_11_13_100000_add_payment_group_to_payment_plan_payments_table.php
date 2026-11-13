<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One handover of money, found again from any of the rows it wrote.
 *
 * A student who pays on a payment plan can now settle several instalments,
 * and carry the rest onto another instalment fee, in one handover. Fee
 * receipts already carry a payment_group for exactly this; plan payments did
 * not, so the receipt could only ever describe the last row written and the
 * student was handed a sheet for 33,333 having given 70,000.
 *
 * Same column, same width, same meaning as on payment_receipts, so one group
 * reference ties both kinds of row together.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_plan_payments', 'payment_group')) {
            return;
        }

        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->string('payment_group', 40)->nullable()->after('reference_no');
            $table->index('payment_group');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('payment_plan_payments', 'payment_group')) {
            return;
        }

        Schema::table('payment_plan_payments', function (Blueprint $table) {
            $table->dropIndex(['payment_group']);
            $table->dropColumn('payment_group');
        });
    }
};
