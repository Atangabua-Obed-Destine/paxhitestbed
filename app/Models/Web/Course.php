<?php

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Language;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'language_id', 'title', 'slug', 'faculty', 'semesters', 'credits', 'courses', 'duration', 'fee', 'description', 'attach', 'status',
    ];

    public function language()
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}
