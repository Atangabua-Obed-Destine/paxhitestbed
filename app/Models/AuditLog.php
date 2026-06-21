<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\User;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'user_type',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Accessor to fix the user_type namespace issue.
     */
    public function getUserTypeAttribute($value)
    {
        if ($value === 'App\Models\User') {
            return 'App\User';
        }
        return $value;
    }

    /**
     * Accessor to fix the auditable_type namespace issue.
     */
    public function getAuditableTypeAttribute($value)
    {
        if ($value === 'App\Models\User') {
            return 'App\User';
        }
        return $value;
    }

    /**
     * Get the user that performed the action (polymorphic relationship).
     * This can be either a User or a Student.
     */
    public function user()
    {
        return $this->morphTo('user');
    }

    /**
     * Get the auditable model (polymorphic relationship).
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Get a human-readable event name.
     */
    public function getEventNameAttribute()
    {
        $events = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'logged_in' => 'Logged In',
            'logged_out' => 'Logged Out',
            'mark_submitted' => 'Mark Submitted',
            'mark_updated' => 'Mark Updated',
            'fee_payment' => 'Fee Payment',
            'student_enrolled' => 'Student Enrolled',
            'attendance_marked' => 'Attendance Marked',
        ];

        return $events[$this->event] ?? ucfirst(str_replace('_', ' ', $this->event));
    }

    /**
     * Get the model name without namespace.
     */
    public function getModelNameAttribute()
    {
        if (!$this->auditable_type) {
            return 'N/A';
        }

        $parts = explode('\\', $this->auditable_type);
        return end($parts);
    }

    /**
     * Get changes made (comparison of old and new values).
     */
    public function getChangesAttribute()
    {
        // Ensure we have arrays
        $oldValues = is_array($this->old_values) ? $this->old_values : json_decode($this->old_values, true);
        $newValues = is_array($this->new_values) ? $this->new_values : json_decode($this->new_values, true);

        if (!$oldValues || !$newValues || !is_array($oldValues) || !is_array($newValues)) {
            return [];
        }

        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get the user's display name (works for both User and Student).
     */
    public function getUserNameAttribute()
    {
        if (!$this->user) {
            return 'System';
        }

        // Check if it's a Student
        if ($this->user_type === 'App\Models\Student') {
            $firstName = $this->user->first_name ?? '';
            $lastName = $this->user->last_name ?? '';
            return trim($firstName . ' ' . $lastName);
        }

        // Otherwise it's a User
        return $this->user->name ?? 'Unknown User';
    }

    /**
     * Get the user's identifier (email for User, student ID for Student).
     */
    public function getUserIdentifierAttribute()
    {
        if (!$this->user) {
            return 'N/A';
        }

        // Check if it's a Student
        if ($this->user_type === 'App\Models\Student') {
            return 'Student ID: ' . $this->user->id;
        }

        // Otherwise it's a User
        return $this->user->email ?? 'N/A';
    }
}

