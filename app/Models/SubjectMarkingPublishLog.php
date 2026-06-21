<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubjectMarkingPublishLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subject_marking_id',
        'action',
        'reason',
        'performed_by',
        'previous_state',
        'new_state',
    ];

    /**
     * Get the subject marking record.
     */
    public function subjectMarking()
    {
        return $this->belongsTo(SubjectMarking::class, 'subject_marking_id');
    }

    /**
     * Get the user who performed the action.
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
