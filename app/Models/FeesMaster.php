<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class FeesMaster extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'category_id', 'faculty_id', 'program_id', 'session_id', 'semester_id', 'section_id', 'amount', 'type', 'assign_date', 'due_date', 'status', 'created_by', 'updated_by',
    ];

    public function studentEnrolls()
    {
        return $this->belongsToMany(StudentEnroll::class, 'fees_master_student_enroll', 'fees_master_id', 'student_enroll_id');
    }

    public function category()
    {
        return $this->belongsTo(FeesCategory::class, 'category_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        // Load category relationship if not loaded
        try {
            if (!$this->relationLoaded('category') && $this->category_id) {
                $this->load('category');
            }
        } catch (\Exception $e) {
            // Silently handle loading errors
        }
        
        // Get category name with fallback
        $categoryName = 'Unknown Category';
        if ($this->category) {
            $categoryName = $this->category->title ?? 'Category #' . $this->category_id;
        } elseif ($this->category_id) {
            $category = \App\Models\FeesCategory::find($this->category_id);
            $categoryName = $category ? ($category->title ?? 'Category #' . $this->category_id) : 'Category #' . $this->category_id;
        }
        
        $amount = number_format($this->amount ?? 0, 2);
        $type = $this->type ?? 'N/A';
        $status = $this->status ?? 'N/A';
        
        return "Fees Master {$event}: {$categoryName} - Type: {$type}, Amount: {$amount}, Status: {$status}";
    }
}
