<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultContribution extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_id', 'attendances', 'assignments', 'activities', 'status',
    ];
    
    /**
     * Get the subject that owns the contribution.
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
