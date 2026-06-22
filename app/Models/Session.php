<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Session extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'start_date', 'end_date', 'current', 'status', 'applications_open',
    ];

    protected $casts = [
        'applications_open' => 'boolean',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_session', 'session_id', 'program_id');
    }

    public function studentEnrolls()
    {
        return $this->hasMany(StudentEnroll::class, 'session_id');
    }

    public function classes()
    {
        return $this->hasMany(ClassRoutine::class, 'session_id', 'id');
    }

    public function contents()
    {
        return $this->hasMany(Content::class, 'session_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $startDate = $this->start_date ?? 'N/A';
        $endDate = $this->end_date ?? 'N/A';
        $current = $this->current == '1' ? 'Yes' : 'No';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Session {$event}: {$title} ({$startDate} - {$endDate}), Current: {$current}, Status: {$status}";
    }
}
