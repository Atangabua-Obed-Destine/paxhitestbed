<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Traits\Auditable;

class StaffAssignment extends Model
{
    use HasFactory, Auditable;
    
    protected $fillable = [
        'user_id',
        'assignable_type',
        'assignable_id',
        'created_by',
    ];
    
    /**
     * Get the staff member (user) assigned
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    /**
     * Get the assignable entity (Faculty, Program, Course)
     */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
    
    /**
     * Get the user who created this assignment
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Check if a staff has assignment for a specific model
     */
    public static function hasAssignment($userId, $type, $id): bool
    {
        return self::where('user_id', $userId)
            ->where('assignable_type', $type)
            ->where('assignable_id', $id)
            ->exists();
    }
    
    /**
     * Get all assignments for a user by type
     */
    public static function getAssignmentsByType($userId, $type)
    {
        return self::where('user_id', $userId)
            ->where('assignable_type', $type)
            ->pluck('assignable_id')
            ->toArray();
    }
    
    /**
     * Check if user has any assignments
     */
    public static function hasAnyAssignments($userId): bool
    {
        return self::where('user_id', $userId)->exists();
    }
}
