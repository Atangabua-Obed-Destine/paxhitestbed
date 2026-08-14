<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSemesterFee extends Model
{
    use Auditable;

    protected $fillable = [
        'program_id',
        'semester_id',
        'fees_category_id',
        'amount',
        'due_month',
        'due_day',
        'fine_amount',
        'fine_type',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fine_amount' => 'decimal:2',
        'due_month' => 'integer',
        'due_day' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Get the program
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * Get the semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    /**
     * Get the fees category
     */
    public function feesCategory(): BelongsTo
    {
        return $this->belongsTo(FeesCategory::class, 'fees_category_id');
    }

    /**
     * Get the fee breakdowns (for Form A2)
     */
    public function breakdowns()
    {
        return $this->hasMany(ProgramSemesterFeeBreakdown::class, 'program_semester_fee_id')->orderBy('order');
    }

    /**
     * Get audit description
     */
    public function getAuditDescription($event)
    {
        $program = $this->program->title ?? 'Unknown Program';
        $semester = $this->semester->title ?? 'Unknown Semester';
        $category = $this->feesCategory->title ?? 'Unknown Category';
        $amount = $this->amount;

        return "Program Semester Fee {$event}: {$program} - {$semester} - {$category} ({$amount})";
    }
}
