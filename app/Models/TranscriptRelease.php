<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;

class TranscriptRelease extends Model
{
    use Auditable;

    protected $fillable = [
        'student_id',
        'session_id',
        'payment_id',
        'released_at',
        'released_by',
        'notes',
    ];

    protected $casts = [
        'released_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function releaser()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
