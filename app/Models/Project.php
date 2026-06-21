<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'faculty_id', 'title', 'description', 'lead_researcher', 'theme', 
        'status', 'attach', 'start_date', 'end_date', 'featured', 'is_active',
    ];

    /**
     * Get the faculty that owns the project.
     */
    public function faculty()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id', 'id');
    }
}
