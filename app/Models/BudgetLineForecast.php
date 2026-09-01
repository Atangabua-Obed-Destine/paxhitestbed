<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * The assumption behind a budgeted tuition figure.
 *
 * "9,450,000" says nothing; "37 students at 255,405" can be argued with, checked
 * against last year, and recalculated when fees move. This is that sentence,
 * stored.
 */
class BudgetLineForecast extends Model
{
    use Auditable;

    protected $fillable = [
        'budget_id',
        'budget_line_id',
        'student_count',
        'rate',
        'rate_basis',
        'computed_amount',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'student_count' => 'integer',
        'rate' => 'decimal:2',
        'computed_amount' => 'decimal:2',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class);
    }

    /**
     * Has the configured fee moved since this was saved?
     *
     * Reported rather than applied. A budget that recalculates itself between
     * viewings is worse than one that is out of date and says so — particularly
     * once somebody has approved the figure.
     */
    public function isStaleAgainst(float $currentRate): bool
    {
        if ($this->rate_basis !== 'weighted') {
            // A rate typed by hand is a decision, not a derivation, so a change
            // in the configured fees does not make it wrong.
            return false;
        }

        return abs((float) $this->rate - $currentRate) > 0.01;
    }
}
