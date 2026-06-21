<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Traits\Auditable;

class FeesDiscount extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'start_date', 'end_date', 'amount', 'type', 'status',
    ];

    public function feesCategories()
    {
        return $this->belongsToMany(FeesCategory::class, 'fees_category_fees_discount', 'fees_discount_id', 'fees_category_id');
    }

    public function statusTypes()
    {
        return $this->belongsToMany(StatusType::class, 'fees_discount_status_type', 'fees_discount_id', 'status_type_id');
    }


    // Check if the student is eligible for the discount based on their statuses
    public static function availability($discount, $student)
    {
        $discount_type = FeesDiscount::where('id', $discount)
                            ->where('status', '1')->first();

        foreach($discount_type->statusTypes as $statusType){

            $availability = Student::where('id', $student)->with('statuses')->whereHas('statuses', function ($query) use ($statusType){
                $query->where('status_type_id', $statusType->id);
            })->first();

            if($availability == true){
                return $availability;
                break;
            }

        }
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $amount = number_format($this->amount ?? 0, 2);
        $type = $this->type ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Fees Discount {$event}: {$title} - Type: {$type}, Amount: {$amount}, Status: {$status}";
    }
}
