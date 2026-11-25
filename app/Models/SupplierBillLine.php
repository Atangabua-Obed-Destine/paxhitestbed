<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a supplier bill, filed under an expense category. */
class SupplierBillLine extends Model
{
    protected $fillable = [
        'supplier_bill_id', 'line_number', 'description', 'expense_category_id',
        'account_id', 'amount', 'journal_entry_line_id', 'void_journal_entry_line_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function bill()
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
