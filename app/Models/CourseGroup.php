<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Courses that are one course taught to several programmes, each under the
 * programme's own code. See App\Services\Academic\CourseGroups for the rules
 * and for the "same course?" question every screen asks.
 */
class CourseGroup extends Model
{
    use Auditable;

    protected $fillable = ['title', 'note', 'status', 'created_by', 'updated_by'];

    protected $casts = ['status' => 'boolean'];

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'course_group_id')->orderBy('code');
    }

    public function getAuditDescription($event)
    {
        return "Course group {$event}: {$this->title}";
    }
}
