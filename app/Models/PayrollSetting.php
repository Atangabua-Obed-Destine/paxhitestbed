<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The choices a school makes about how its payroll runs. One row.
 *
 * Every default is how payroll behaved before these settings existed.
 */
class PayrollSetting extends Model
{
    protected $fillable = [
        'require_approval', 'carry_over_lines', 'prorate_partial_months', 'proration_basis',
        'staff_payslips_enabled', 'email_payslip_on_pay',
        'bank_file_columns', 'bank_file_delimiter', 'bank_file_header', 'bank_file_reference', 'bank_file_decimals',
        'advance_account_id', 'advance_payment_account_id', 'updated_by',
    ];

    protected $casts = [
        'require_approval' => 'boolean',
        'carry_over_lines' => 'boolean',
        'prorate_partial_months' => 'boolean',
        'staff_payslips_enabled' => 'boolean',
        'email_payslip_on_pay' => 'boolean',
        'bank_file_header' => 'boolean',
        'bank_file_columns' => 'array',
        'bank_file_decimals' => 'integer',
    ];

    /** Columns the bank file can carry, in the order offered. */
    public const BANK_COLUMNS = [
        'staff_id' => 'Staff ID',
        'name' => 'Staff name',
        'account_name' => 'Account holder',
        'bank_name' => 'Bank',
        'branch' => 'Branch',
        'account_number' => 'Account number',
        'bank_code' => 'Bank / sort code',
        'amount' => 'Amount (net pay)',
        'currency' => 'Currency',
        'reference' => 'Payment reference',
        'month' => 'Salary month',
    ];

    public const DEFAULT_BANK_COLUMNS = ['staff_id', 'name', 'bank_name', 'account_number', 'amount', 'reference'];

    public const PRORATION_BASES = [
        'calendar_days' => 'Calendar days in the month',
        'working_days' => 'Working days (Monday to Friday)',
    ];

    private static ?self $cached = null;

    /** The settings, created with the defaults the first time they are asked for. */
    public static function current(): self
    {
        if (static::$cached && static::$cached->exists) {
            return static::$cached;
        }

        if (!Schema::hasTable('payroll_settings')) {
            return static::$cached = new static(static::defaults());
        }

        return static::$cached = static::query()->first() ?? static::create(static::defaults());
    }

    /** Forget the cached row, after saving or between tests. */
    public static function flush(): void
    {
        static::$cached = null;
    }

    public static function defaults(): array
    {
        return [
            'require_approval' => false,
            'carry_over_lines' => true,
            'prorate_partial_months' => false,
            'proration_basis' => 'calendar_days',
            'staff_payslips_enabled' => true,
            'email_payslip_on_pay' => false,
            'bank_file_columns' => self::DEFAULT_BANK_COLUMNS,
            'bank_file_delimiter' => ',',
            'bank_file_header' => true,
            'bank_file_reference' => 'SALARY {MONTH} {YEAR}',
            'bank_file_decimals' => 0,
        ];
    }

    public function bankColumns(): array
    {
        $columns = array_values(array_filter(
            (array) ($this->bank_file_columns ?: self::DEFAULT_BANK_COLUMNS),
            fn ($c) => isset(self::BANK_COLUMNS[$c])
        ));

        return $columns ?: self::DEFAULT_BANK_COLUMNS;
    }

    public function advanceAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'advance_account_id');
    }

    public function advancePaymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'advance_payment_account_id');
    }
}
