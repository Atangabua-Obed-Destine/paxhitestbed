<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Install the payment account types (cash on hand, bank account, mobile
 * money...) where there are none.
 *
 * The type is required to create a payment account, there is no screen to
 * create types, and PaymentAccountTypeSeeder was never called by the database
 * seeder — so on an install where it had not been run by hand, no cash box,
 * bank account or wallet could be created at all, and nothing could be linked
 * to the ledger. A school that already has types keeps exactly what it has.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('payment_account_types')->exists()) {
            return;
        }

        (new \Database\Seeders\PaymentAccountTypeSeeder())->run();
    }

    public function down(): void
    {
        // The types may be in use by payment accounts by then; they stay.
    }
};
