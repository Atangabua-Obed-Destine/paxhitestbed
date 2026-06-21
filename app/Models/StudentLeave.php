<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\User;
use App\Traits\Auditable;

class StudentLeave extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'student_id', 'review_by', 'apply_date', 'from_date', 'to_date', 'subject', 'reason', 'attach', 'note', 'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function reviewBy()
    {
        return $this->belongsTo(User::class, 'review_by', 'id');
    }
}
