<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * What payroll needs to be run for a whole staff rather than one person at a
 * time: settings for the choices a school makes differently (approval, bank
 * file layout, proration, emailing), an approval stamp on each payroll, the
 * days a part-month payroll was paid for, and staff loans and advances repaid
 * through payroll.
 *
 * Every setting defaults to how payroll behaved before, so applying this
 * changes nothing until somebody changes a setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payroll_settings')) {
            Schema::create('payroll_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('require_approval')->default(false);
                $table->boolean('carry_over_lines')->default(true);
                $table->boolean('prorate_partial_months')->default(false);
                $table->string('proration_basis', 20)->default('calendar_days');
                $table->boolean('staff_payslips_enabled')->default(true);
                $table->boolean('email_payslip_on_pay')->default(false);
                $table->json('bank_file_columns')->nullable();
                $table->string('bank_file_delimiter', 5)->default(',');
                $table->boolean('bank_file_header')->default(true);
                $table->string('bank_file_reference')->default('SALARY {MONTH} {YEAR}');
                $table->unsignedTinyInteger('bank_file_decimals')->default(0);
                $table->unsignedBigInteger('advance_account_id')->nullable();
                $table->unsignedBigInteger('advance_payment_account_id')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('payrolls', 'paid_days')) {
                $table->decimal('paid_days', 6, 2)->nullable()->after('hours_worked');
            }
            if (!Schema::hasColumn('payrolls', 'period_days')) {
                $table->decimal('period_days', 6, 2)->nullable()->after('paid_days');
            }
            if (!Schema::hasColumn('payrolls', 'payslip_emailed_at')) {
                $table->timestamp('payslip_emailed_at')->nullable();
            }
        });

        if (!Schema::hasTable('staff_loans')) {
            Schema::create('staff_loans', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('kind', 10)->default('loan'); // loan | advance
                $table->string('title');
                $table->decimal('amount', 15, 2);
                $table->decimal('instalment', 15, 2);
                $table->date('start_month');
                $table->date('given_on');
                $table->string('status', 12)->default('active'); // active | cancelled
                $table->text('note')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable();
                $table->unsignedBigInteger('reversal_entry_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('payroll_details', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_details', 'staff_loan_id')) {
                $table->unsignedBigInteger('staff_loan_id')->nullable()->index()->after('payroll_id');
            }
        });

        // Permissions, granted to Super Admin and to whoever already runs
        // payroll. Approving is deliberately separate so a school can give it
        // to someone other than the person who generates.
        try {
            $grants = [
                'payroll-approve' => ['payroll-action'],
                'payroll-setting' => ['payroll-action'],
                'staff-loan-view' => ['payroll-action', 'payroll-view'],
                'staff-loan-action' => ['payroll-action'],
            ];

            foreach ($grants as $name => $from) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

                foreach (Role::all() as $role) {
                    $give = $role->name === 'Super Admin';
                    foreach ($from as $existing) {
                        if (!$give) {
                            try { $give = $role->hasPermissionTo($existing); } catch (\Throwable $e) { $give = false; }
                        }
                    }
                    // Approval is not handed out automatically beyond Super
                    // Admin: the point of it is that someone chooses who.
                    if ($name === 'payroll-approve' && $role->name !== 'Super Admin') {
                        $give = false;
                    }
                    if ($give) {
                        $role->givePermissionTo($permission);
                    }
                }
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Non-fatal: the permissions can be created from the roles screen.
        }
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_details', 'staff_loan_id')) {
                $table->dropColumn('staff_loan_id');
            }
        });
        Schema::dropIfExists('staff_loans');
        Schema::table('payrolls', function (Blueprint $table) {
            foreach (['approved_by', 'approved_at', 'paid_days', 'period_days', 'payslip_emailed_at'] as $column) {
                if (Schema::hasColumn('payrolls', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('payroll_settings');

        try {
            Permission::whereIn('name', ['payroll-approve', 'payroll-setting', 'staff-loan-view', 'staff-loan-action'])->delete();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        } catch (\Throwable $e) {
        }
    }
};
