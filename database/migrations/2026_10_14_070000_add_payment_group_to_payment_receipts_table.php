<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One handover of money can now settle more than one fee.
 *
 * A payment receipt is still per fee — that is what the reports, the unlinked
 * transaction audit and the ledger all expect, and none of it changes. This
 * column simply says which receipts came from the same handover, so a receipt
 * can be printed showing every instalment that one payment settled.
 *
 * Null for every receipt written before this, and for every ordinary single-fee
 * payment after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_receipts', 'payment_group')) {
            return;
        }

        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->string('payment_group', 40)->nullable()->after('payment_reference');
            $table->index('payment_group');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('payment_receipts', 'payment_group')) {
            return;
        }

        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropIndex(['payment_group']);
            $table->dropColumn('payment_group');
        });
    }
};
