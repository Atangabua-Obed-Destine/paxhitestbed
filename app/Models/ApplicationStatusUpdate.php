<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'stage',
        'status',
        'title',
        'note',
        'is_visible_to_applicant',
        'created_by',
        'created_by_type',
    ];

    protected $casts = [
        'is_visible_to_applicant' => 'boolean',
    ];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function creator()
    {
    return $this->belongsTo(\App\User::class, 'created_by');
    }
}
