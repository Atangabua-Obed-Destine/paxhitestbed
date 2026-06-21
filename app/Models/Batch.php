<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Batch extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'start_date', 'status',
    ];

    public function programs()
    {
        return $this->belongsToMany(Program::class, 'batch_program', 'batch_id', 'program_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'batch_id', 'id');
    }

    /**
     * Custom audit description
     */
    public function getAuditDescription($event)
    {
        $title = $this->title ?? 'N/A';
        $startDate = $this->start_date ?? 'N/A';
        $status = $this->status == '1' ? 'Active' : 'Inactive';
        
        return "Batch {$event}: {$title}, Start Date: {$startDate}, Status: {$status}";
    }
}
