<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ProgramAssessmentConfig extends Model
{
    use Auditable;

    protected $fillable = [
        'program_id',
        'semester_id',
        'subject_id',
        'effective_from',
        'effective_to',
        'exam_weight',
        'ca_weight',
        'attendance_weight',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'exam_weight' => 'float',
        'ca_weight' => 'float',
        'attendance_weight' => 'float',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
