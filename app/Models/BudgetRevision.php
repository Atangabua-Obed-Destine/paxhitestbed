<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\User;

class BudgetRevision extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'budget_id',
        'revision_number',
        'previous_amount',
        'new_amount',
        'change_amount',
        'change_type',
        'reason',
        'justification',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'previous_amount' => 'decimal:2',
        'new_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Accessors & Helpers
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'approved' => '<span class="badge badge-success">Approved</span>',
            'rejected' => '<span class="badge badge-danger">Rejected</span>',
        ];

        return $badges[$this->status] ?? '<span class="badge badge-secondary">Unknown</span>';
    }

    public function getChangeDescriptionAttribute()
    {
        $direction = $this->change_type == 'increase' ? 'increased' : 'decreased';
        $from = number_format($this->previous_amount, 2);
        $to = number_format($this->new_amount, 2);
        $change = number_format(abs($this->change_amount), 2);
        
        return "Budget {$direction} from {$from} to {$to} (change: {$change})";
    }

    /**
     * Methods
     */
    public function approve($approverId)
    {
        $this->status = 'approved';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->save();

        // Apply the revision to the budget
        $budget = $this->budget;
        $budget->total_amount = $this->new_amount;
        $budget->remaining_amount = $budget->total_amount - $budget->spent_amount;
        $budget->save();

        return true;
    }

    public function reject($approverId, $reason)
    {
        $this->status = 'rejected';
        $this->approved_by = $approverId;
        $this->approved_at = now();
        $this->rejected_reason = $reason;
        $this->save();

        return true;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($revision) {
            // Auto-increment revision number
            $lastRevision = static::where('budget_id', $revision->budget_id)
                ->orderBy('revision_number', 'desc')
                ->first();
            
            $revision->revision_number = $lastRevision ? $lastRevision->revision_number + 1 : 1;

            // Calculate change amount if not provided
            if (!$revision->change_amount) {
                $revision->change_amount = $revision->new_amount - $revision->previous_amount;
            }

            // Determine change type if not provided
            if (!$revision->change_type) {
                $revision->change_type = $revision->change_amount >= 0 ? 'increase' : 'decrease';
            }
        });
    }
}
