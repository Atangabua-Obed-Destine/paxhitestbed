<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment of withheld tax to the body it was withheld for.
 *
 * Until now nothing recorded this. Tax accumulated in 443 and 431 and stayed
 * there, because clearing it meant hand-typing a journal entry — so in
 * practice it was never cleared, and the balance grew every month with no
 * screen anywhere saying the school was holding money that belonged to
 * somebody else.
 *
 * One row is one declaration: this authority, this salary month, this amount,
 * paid on this date from this account, under this receipt number. That is the
 * shape an inspection asks in, and it is what makes "August is still
 * undeclared" a thing the system can say.
 *
 * There is no unique index on (authority, month). A voided remittance still
 * occupies its row — it has to, that is the audit trail — and MySQL cannot
 * express "unique among the rows that are not voided". The one-payment-per-
 * month rule is enforced in TaxRemittanceService, where the voided ones can be
 * excluded, and pinned by the test suite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_remittances', function (Blueprint $table) {
            $table->id();

            // The authority, as a chart-of-accounts liability: 443 Etat,
            // 431 CNPS. See the liability_account_id migration for why the
            // account is the authority.
            $table->unsignedBigInteger('liability_account_id');

            // Always the first of the month. The month the pay related to —
            // not the month it was handed over, which is payment_date.
            $table->date('salary_month');

            $table->decimal('amount', 15, 2);
            $table->date('payment_date');

            // Which bank or cash account it left. Class 5 only; the form
            // offers nothing else.
            $table->unsignedBigInteger('source_account_id');

            // The receipt or declaration number the authority issued. Left
            // free-text: DGI and CNPS number things differently, and a format
            // guessed here would only be wrong somewhere.
            $table->string('reference', 191)->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('journal_entry_id')->nullable();

            // Voiding writes a reversing entry rather than deleting the row,
            // so a correction is visible instead of silent.
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('void_journal_entry_id')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['liability_account_id', 'salary_month'], 'remit_account_month_index');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_remittances');
    }
};
