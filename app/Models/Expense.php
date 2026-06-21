<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class Expense extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id', 'title', 'invoice_id', 'amount', 'date', 'reference', 'payment_method', 'payment_account_id', 'note', 'attach', 'status', 'created_by', 'updated_by',
        'budget_id', 'budget_allocation_id', 'approval_status', 'approved_by', 'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'payment_account_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function budgetAllocation()
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scopes
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeWithBudget($query)
    {
        return $query->whereNotNull('budget_id');
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        // Update budget amounts when expense is approved
        static::updated(function ($expense) {
            if ($expense->isDirty('approval_status') && $expense->approval_status == 'approved') {
                if ($expense->budget_id) {
                    $expense->budget->updateSpentAmount();
                }
                if ($expense->budget_allocation_id) {
                    $expense->budgetAllocation->updateSpentAmount();
                }
            }
        });
    }
}
