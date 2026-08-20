<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class BudgetAllocation extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'budget_id',
        'expense_category_id',
        'budget_line_id',
        'department_id',
        'title',
        'allocated_amount',
        'spent_amount',
        'committed_amount',
        'remaining_amount',
        'period',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    /**
     * The Income & Expenditure sheet line this allocation belongs to.
     *
     * Null for departmental allocations, which use an expense category instead.
     * Exactly one of the two is set.
     */
    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class, 'budget_line_id');
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'budget_allocation_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Accessors & Helpers
     */
    public function getUtilizationPercentageAttribute()
    {
        if ($this->allocated_amount == 0) return 0;
        return round(($this->spent_amount / $this->allocated_amount) * 100, 2);
    }

    public function getAvailableAmountAttribute()
    {
        return $this->allocated_amount - $this->spent_amount - $this->committed_amount;
    }

    public function getStatusColorAttribute()
    {
        $percentage = $this->utilization_percentage;
        
        if ($percentage < 50) return 'success';
        if ($percentage < 75) return 'info';
        if ($percentage < 90) return 'warning';
        return 'danger';
    }

    /**
     * Methods
     */
    public function updateSpentAmount()
    {
        // Single source of truth: an allocation's spend = sum of its own non-rejected expenses.
        // (committed_amount is unused/always 0; kept out of the remaining formula.)
        $this->spent_amount = $this->expenses()->where('approval_status', '!=', 'rejected')->sum('amount');
        $this->remaining_amount = $this->allocated_amount - $this->spent_amount;
        $this->save();

        // Cascade to the parent budget
        if ($this->budget) {
            $this->budget->updateSpentAmount();
        }
    }

    public function canAddExpense($amount)
    {
        return $this->available_amount >= $amount;
    }

    public function isOverBudget()
    {
        return $this->spent_amount > $this->allocated_amount;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($allocation) {
            $allocation->remaining_amount = $allocation->allocated_amount;
        });

        static::updating(function ($allocation) {
            $allocation->remaining_amount = $allocation->allocated_amount - $allocation->spent_amount - $allocation->committed_amount;
        });
    }
}
