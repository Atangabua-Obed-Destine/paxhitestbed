<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChartOfAccount;
use App\Models\FeesCategory;
use App\Models\IncomeCategory;
use App\Models\ExpenseCategory;
use App\User;
use App\Traits\Auditable;

class DefaultAccountMapping extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'mapping_type',
        'budget_line_id',
        'category_id',
        'debit_account_id',
        'credit_account_id',
        'description',
        'status',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the debit account for the mapping
     */
    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    /**
     * Get the credit account for the mapping
     */
    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    /**
     * Get the creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the category (polymorphic relationship)
     */
    public function category()
    {
        // Return the appropriate model based on mapping_type
        switch ($this->mapping_type) {
            case 'fee_category':
                return $this->belongsTo(FeesCategory::class, 'category_id');
            case 'income_category':
                return $this->belongsTo(IncomeCategory::class, 'category_id');
            case 'expense_category':
                return $this->belongsTo(ExpenseCategory::class, 'category_id');
            case 'payroll':
                return null; // Payroll doesn't have a category
            default:
                return null;
        }
    }

    /**
     * Get fee category if applicable
     */
    public function feeCategory()
    {
        return $this->belongsTo(FeesCategory::class, 'category_id');
    }

    /**
     * Get income category if applicable
     */
    public function incomeCategory()
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id');
    }

    /**
     * Get expense category if applicable
     */
    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Scope to filter by mapping type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('mapping_type', $type);
    }

    /**
     * Scope to get active mappings
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get inactive mappings
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Get the category name based on type
     */
    public function getCategoryNameAttribute()
    {
        if ($this->mapping_type === 'payroll') {
            return 'Payroll';
        }

        $category = $this->category();
        return $category ? $category->first()?->name : null;
    }

    /**
     * Check if this is a payroll mapping
     */
    public function isPayrollMapping()
    {
        return $this->mapping_type === 'payroll';
    }
}
