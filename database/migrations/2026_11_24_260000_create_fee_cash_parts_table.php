<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee cash on the day it came in.
 *
 * A fee has one date column, pay_date, which every new payment overwrites. Its
 * ledger entry used to carry all its cash on that date, so a fee paid in
 * October and again in December showed nothing in October and everything in
 * December. The receipts know better: each has its own date and method.
 *
 * Each row here is the cash of one fee received on one earlier day into one
 * account (from its approved receipts dated before the fee's pay_date), posted
 * as its own entry on that day. The fee's own entry carries the rest, on its
 * pay_date. Rows are never deleted: a part no longer right is reversed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_cash_parts')) {
            Schema::create('fee_cash_parts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('fee_id');
                $table->date('entry_date');
                $table->unsignedBigInteger('debit_account_id');
                $table->unsignedBigInteger('credit_account_id');
                $table->decimal('amount', 15, 2);
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->string('status', 16)->default('active');
                $table->unsignedBigInteger('reversal_entry_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['fee_id', 'status']);
                $table->index('entry_date');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_cash_parts');
    }
};
