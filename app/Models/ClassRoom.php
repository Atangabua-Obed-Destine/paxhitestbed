<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ClassRoom extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'floor', 'capacity', 'type', 'description', 'status',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_class_room', 'class_room_id', 'program_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'room_id', 'id');
    }

    public function examRoutines()
    {
        return $this->belongsToMany(ExamRoutine::class, 'exam_routine_room', 'room_id', 'exam_routine_id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $floor = $this->floor ?? 'N/A';
        $capacity = $this->capacity ?? 'N/A';
        $type = $this->type ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Classroom {$event}: {$title} (Floor: {$floor}, {$type}), Capacity: {$capacity}, Status: {$status}";
    }
}
