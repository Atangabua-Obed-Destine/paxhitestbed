<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\User;

class ClassSessionMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'class_session_id',
        'student_id',
        'user_id',
        'sender_type',
        'message',
        'is_pinned',
        'is_announcement',
        'reply_to_id',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_announcement' => 'boolean',
    ];

    const SENDER_STUDENT = 'student';
    const SENDER_LECTURER = 'lecturer';
    const SENDER_SYSTEM = 'system';

    /**
     * Get the class session
     */
    public function classSession()
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * Get the student (if sender is student)
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the user/lecturer (if sender is lecturer)
     */
    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    /**
     * Get the message this is replying to
     */
    public function replyTo()
    {
        return $this->belongsTo(ClassSessionMessage::class, 'reply_to_id');
    }

    /**
     * Get replies to this message
     */
    public function replies()
    {
        return $this->hasMany(ClassSessionMessage::class, 'reply_to_id');
    }

    /**
     * Get sender name
     */
    public function getSenderNameAttribute()
    {
        if ($this->sender_type === self::SENDER_STUDENT && $this->student) {
            return $this->student->first_name . ' ' . $this->student->last_name;
        } elseif ($this->sender_type === self::SENDER_LECTURER && $this->user) {
            return $this->user->name ?? 'Lecturer';
        } elseif ($this->sender_type === self::SENDER_SYSTEM) {
            return 'System';
        }
        return 'Unknown';
    }

    /**
     * Get sender avatar/photo
     */
    public function getSenderPhotoAttribute()
    {
        if ($this->sender_type === self::SENDER_STUDENT && $this->student) {
            return $this->student->photo;
        } elseif ($this->sender_type === self::SENDER_LECTURER && $this->user) {
            return $this->user->photo ?? null;
        }
        return null;
    }

    /**
     * Scope for pinned messages
     */
    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Scope for announcements
     */
    public function scopeAnnouncements($query)
    {
        return $query->where('is_announcement', true);
    }

    /**
     * Format for broadcasting
     */
    public function toArray()
    {
        $data = parent::toArray();
        $data['sender_name'] = $this->sender_name;
        $data['sender_photo'] = $this->sender_photo;
        $data['formatted_time'] = $this->created_at->format('H:i');
        $data['formatted_date'] = $this->created_at->format('M d, Y');
        return $data;
    }
}
