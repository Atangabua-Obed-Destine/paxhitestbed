<?php

namespace App\Models;

use App\Traits\Auditable;
use App\User;
use Illuminate\Database\Eloquent\Model;

class ExamScriptCode extends Model
{
    use Auditable;

    protected $fillable = [
        'exam_id',
        'exam_routine_id',
        'anonymous_code',
        'generated_by',
        'generated_at',
        'marked_by',
        'marked_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'marked_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function routine()
    {
        return $this->belongsTo(ExamRoutine::class, 'exam_routine_id');
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
