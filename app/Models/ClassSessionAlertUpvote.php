<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSessionAlertUpvote extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'student_id',
    ];

    /**
     * Get the alert
     */
    public function alert()
    {
        return $this->belongsTo(ClassSessionAlert::class, 'alert_id');
    }

    /**
     * Get the student
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
