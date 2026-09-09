<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One tax's share of one payroll, as it was charged on the day.
 *
 * @see database/migrations/2026_09_07_090000_create_payroll_tax_lines_table.php
 */
class PayrollTaxLine extends Model
{
    protected $fillable = [
        'payroll_id', 'user_id', 'salary_month',
        'tax_group_id', 'tax_setting_id', 'label',
        'employee_amount', 'employer_amount', 'liability_account_id',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'employee_amount' => 'decimal:2',
        'employer_amount' => 'decimal:2',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class);
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id');
    }

    public function taxGroup()
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class, 'tax_setting_id');
    }

    /** Everything this line owes, both sides of it. */
    public function getTotalAttribute(): float
    {
        return (float) $this->employee_amount + (float) $this->employer_amount;
    }
}
