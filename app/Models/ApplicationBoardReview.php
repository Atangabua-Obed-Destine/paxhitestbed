<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ApplicationBoardReview extends Model
{
    use Auditable;
    protected $fillable = [
        'application_id',
        'meets_university_requirements',
        'meets_program_requirements',
        'first_choice_decision',
        'second_choice_decision',
        'third_choice_decision',
        'observation',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'meets_university_requirements' => 'boolean',
        'meets_program_requirements' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function signatures()
    {
        return $this->hasMany(ApplicationBoardSignature::class, 'board_review_id');
    }
}
