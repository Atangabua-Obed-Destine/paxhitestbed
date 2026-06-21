<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class FeesCategory extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'description', 'status', 'is_resit', 'is_admission', 'is_first_installment', 'is_second_installment',
    ];

    public function masters()
    {
        return $this->hasMany(FeesMaster::class, 'category_id', 'id');
    }

    public function fees()
    {
        return $this->hasMany(Fee::class, 'category_id', 'id');
    }

    public function fines()
    {
        return $this->belongsToMany(FeesFine::class, 'fees_category_fees_fine', 'fees_category_id', 'fees_fine_id');
    }

    public function discounts()
    {
        return $this->belongsToMany(FeesDiscount::class, 'fees_category_fees_discount', 'fees_category_id', 'fees_discount_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        $isResit = $this->is_resit ? 'Yes' : 'No';
        $isAdmission = $this->is_admission ? 'Yes' : 'No';
        
        return "Fees Category {$event}: {$title}, Status: {$status}, Resit: {$isResit}, Admission: {$isAdmission}";
    }
}
