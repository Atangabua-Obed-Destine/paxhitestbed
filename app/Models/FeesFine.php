<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class FeesFine extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'start_day', 'end_day', 'amount', 'type', 'status',
    ];

    public function feesCategories()
    {
        return $this->belongsToMany(FeesCategory::class, 'fees_category_fees_fine', 'fees_fine_id', 'fees_category_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $startDay = $this->start_day ?? 'N/A';
        $endDay = $this->end_day ?? 'N/A';
        $amount = number_format($this->amount ?? 0, 2);
        $type = $this->type ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Fees Fine {$event}: Days {$startDay}-{$endDay}, Type: {$type}, Amount: {$amount}, Status: {$status}";
    }
}
