<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class StatusType extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'description', 'status',
    ];

    public function students()
    {
        return $this->belongsToMany(Student::class, 'status_type_student', 'status_type_id', 'student_id');
    }

    public function discounts()
    {
        return $this->belongsToMany(FeesDiscount::class, 'fees_discount_status_type', 'status_type_id', 'fees_discount_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $slug = $this->slug ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Status Type {$event}: {$title} ({$slug}), Status: {$status}";
    }
}
