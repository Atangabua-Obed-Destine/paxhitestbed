<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class Budget extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'budget_code',
        'type',
        'department_id',
        'fiscal_year',
        'start_date',
        'end_date',
        'total_amount',
        'allocated_amount',
        'spent_amount',
        'remaining_amount',
        'status',
        'description',
        'note',
        'created_by',
        'updated_by',
        'approved_by',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class, 'budget_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'budget_id');
    }

    public function revisions()
    {
        return $this->hasMany(BudgetRevision::class, 'budget_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Accessors & Helpers
     */
    public function getUtilizationPercentageAttribute()
    {
        if ($this->total_amount == 0) return 0;
        return round(($this->spent_amount / $this->total_amount) * 100, 2);
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'draft' => '<span class="badge badge-secondary">Draft</span>',
            'pending_approval' => '<span class="badge badge-warning">Pending Approval</span>',
            'approved' => '<span class="badge badge-info">Approved</span>',
            'active' => '<span class="badge badge-success">Active</span>',
            'closed' => '<span class="badge badge-dark">Closed</span>',
            'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }

    public function getUtilizationColorAttribute()
    {
        $percentage = $this->utilization_percentage;
        
        if ($percentage < 50) return 'success';
        if ($percentage < 75) return 'info';
        if ($percentage < 90) return 'warning';
        return 'danger';
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByFiscalYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Methods
     */
    public function updateSpentAmount()
    {
        // Single source of truth: a budget's spend = sum of its own expenses that are
        // not rejected (pending + approved count; rejected does not consume budget).
        $this->spent_amount = $this->expenses()->where('approval_status', '!=', 'rejected')->sum('amount');
        $this->remaining_amount = $this->total_amount - $this->spent_amount;
        $this->save();
    }

    public function calculateAllocatedAmount()
    {
        $this->allocated_amount = $this->allocations()->sum('allocated_amount');
        $this->save();
    }

    public function isOverBudget()
    {
        return $this->spent_amount > $this->total_amount;
    }

    public function canAddExpense($amount)
    {
        return ($this->spent_amount + $amount) <= $this->total_amount;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($budget) {
            // Auto-generate budget code
            if (empty($budget->budget_code)) {
                $year = $budget->fiscal_year ?? date('Y');
                $count = static::where('fiscal_year', $year)->count() + 1;
                $budget->budget_code = 'BDG-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }

            // Initialize remaining amount
            $budget->remaining_amount = $budget->total_amount;
        });
    }
}
