<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PayrollDetail extends Model
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'payroll_id', 'title', 'amount', 'status', 'created_by', 'updated_by',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }
    
    /**
     * Custom audit description for better readability
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'Payroll item';
        $amount = $this->amount ?? 0;
        return "Payroll detail {$event}: {$title} - Amount: {$amount}";
    }
}
