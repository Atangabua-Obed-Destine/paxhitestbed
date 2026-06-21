<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ExamType extends Model
{
    use Auditable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'marks', 'contribution', 'is_final', 'description', 'status',
    ];

    protected $casts = [
        'is_final' => 'boolean',
    ];

    public function exams()
    {
        return $this->hasMany(Exam::class, 'exam_type_id', 'id');
    }
}
