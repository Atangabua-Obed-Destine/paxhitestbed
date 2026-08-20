<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow a payment receipt to be marked as reversed.
 *
 * A payment recorded in error has to be undone without being deleted — the
 * receipt, the fee, the payment account and the ledger all have to agree, and a
 * deleted row explains nothing to whoever asks later why the money moved twice.
 *
 * The column is an ENUM of pending/approved/rejected, so 'reversed' would be
 * refused outright (or, on a lenient server, silently written as an empty
 * string, which is worse). Widening the ENUM is the whole change; every existing
 * row keeps the value it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_receipts')) {
            return;
        }

        DB::statement(
            "ALTER TABLE `payment_receipts` MODIFY `verification_status`
             ENUM('pending','approved','rejected','reversed') NOT NULL DEFAULT 'pending'"
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('payment_receipts')) {
            return;
        }

        // Anything reversed becomes rejected again: both mean "this is not
        // money", and the narrower ENUM has no room for the distinction.
        DB::table('payment_receipts')->where('verification_status', 'reversed')
            ->update(['verification_status' => 'rejected']);

        DB::statement(
            "ALTER TABLE `payment_receipts` MODIFY `verification_status`
             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'"
        );
    }
};
