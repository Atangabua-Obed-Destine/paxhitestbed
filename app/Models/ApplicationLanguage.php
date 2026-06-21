<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationLanguage extends Model
{
    protected $fillable = [
        'application_id',
        'language',
        'years_of_study',
        'fluency_level',
    ];

    protected $casts = [
        'years_of_study' => 'integer',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }
}
