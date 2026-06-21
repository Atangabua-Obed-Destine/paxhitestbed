<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramSemesterFeeBreakdown extends Model
{
    use Auditable;

    protected $fillable = [
        'program_semester_fee_id',
        'title',
        'amount',
        'order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'order' => 'integer',
    ];

    /**
     * Get the program semester fee
     */
    public function programSemesterFee(): BelongsTo
    {
        return $this->belongsTo(ProgramSemesterFee::class, 'program_semester_fee_id');
    }

    /**
     * Get audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'Unknown';
        $amount = $this->amount;
        $feeCategory = $this->programSemesterFee->feesCategory->title ?? 'Unknown Category';

        return "Fee Breakdown {$event}: {$title} ({$amount}) for {$feeCategory}";
    }
}
