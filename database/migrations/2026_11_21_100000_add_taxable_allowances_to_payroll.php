<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each allowance type say whether it is taxed, and makes a payroll keep
 * what it was taxed on.
 *
 * Until now the tax was always worked out on the basic salary alone, so a
 * transport or housing allowance was paid but never taxed. Whether an
 * allowance is taxable is a question for the institution's accountant, not
 * for the code, so it becomes a setting on the allowance type. Every type
 * starts as NOT taxable, which is exactly how payrolls were taxed before this
 * change: nothing moves until somebody switches a type on.
 *
 * - allowance_types.is_taxable: the setting.
 * - payroll_details.is_taxable: what the setting was when the payroll was
 *   generated, so a later change to the type does not rewrite an old payslip.
 *   Null on rows written before this existed.
 * - payrolls.taxable_salary: the figure the taxes were charged on (basic plus
 *   the taxable allowances). Null on older payrolls, which were taxed on
 *   total_earning.
 * - payrolls.hours_worked: for hourly staff, the hours the earning was paid
 *   for, so the payslip can show rate x hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('allowance_types', 'is_taxable')) {
            Schema::table('allowance_types', function (Blueprint $table) {
                $table->boolean('is_taxable')->default(false)->after('description');
            });
        }

        if (!Schema::hasColumn('payroll_details', 'is_taxable')) {
            Schema::table('payroll_details', function (Blueprint $table) {
                $table->boolean('is_taxable')->nullable()->after('amount');
            });
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'taxable_salary')) {
                $table->decimal('taxable_salary', 15, 2)->nullable()->after('gross_salary');
            }
            if (!Schema::hasColumn('payrolls', 'hours_worked')) {
                $table->decimal('hours_worked', 8, 2)->nullable()->after('total_earning');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['taxable_salary', 'hours_worked']);
        });
        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });
        Schema::table('allowance_types', function (Blueprint $table) {
            $table->dropColumn('is_taxable');
        });
    }
};
