<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamTypeContribution extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id',
        'exam_type_id',
        'contribution',
    ];
    
    /**
     * Get the subject that owns the contribution.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
    
    /**
     * Get the exam type that owns the contribution.
     */
    public function examType()
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }
}
