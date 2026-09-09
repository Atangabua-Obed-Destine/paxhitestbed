<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each tax actually took from a payroll.
 *
 * `payrolls` stores one figure — tax = 21,282 — and nothing records that it was
 * 7,929 income tax, 1,250 local development, 1,950 CRTV, 1,800 housing fund,
 * 793 council tax and 7,560 CNPS. Two things follow from that.
 *
 * The payslip re-derives the split from whatever the configuration says at the
 * moment it prints, so a payslip reprinted after a rate change shows figures
 * the staff member was never paid. And a monthly declaration to DGI or CNPS
 * cannot be filled in from what the system holds, because the system does not
 * hold it.
 *
 * These rows are written when a payroll is paid, from the configuration that
 * was effective for its salary month. They are a record of what happened, not
 * a calculation to be repeated.
 *
 * `salary_month` and `user_id` are carried here rather than joined back for,
 * because every question the remittance screen asks is "what was withheld in
 * this month, for this authority" — and a payroll deleted outright should not
 * silently take the evidence with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_tax_lines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('payroll_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->date('salary_month');

            // One of the two identifies the tax. A grouped tax names its group
            // and the bracket that was applied; a standalone tax names only
            // itself.
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->unsignedBigInteger('tax_setting_id')->nullable();

            // The name as it read on the day, so a tax later renamed still
            // reads correctly on an old declaration.
            $table->string('label', 191);

            $table->decimal('employee_amount', 15, 2)->default(0);
            $table->decimal('employer_amount', 15, 2)->default(0);

            // The account this accrued to — the authority it is owed to.
            $table->unsignedBigInteger('liability_account_id')->nullable();

            $table->timestamps();

            $table->index('payroll_id');
            $table->index(['salary_month', 'liability_account_id'], 'ptl_month_account_index');
            $table->index('tax_setting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_tax_lines');
    }
};
