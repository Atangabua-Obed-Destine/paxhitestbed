<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class ExamAttendanceSetting extends Model
{
    use HasFactory, Auditable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'minimum_attendance_percentage',
        'is_enabled',
        'updated_by',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'minimum_attendance_percentage' => 'decimal:2',
        'is_enabled' => 'boolean',
    ];
    
    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy()
    {
        return $this->belongsTo(\App\User::class, 'updated_by');
    }
}
