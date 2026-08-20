<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One chat thread, belonging to a guest, applicant, student or staff user.
 */
class ChatConversation extends Model
{
    use HasFactory;

    public const ACTOR_GUEST = 'guest';
    public const ACTOR_APPLICANT = 'applicant';
    public const ACTOR_STUDENT = 'student';
    public const ACTOR_USER = 'user';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'session_key',
        'surface',
        'title',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('id');
    }

    /**
     * Resolve the actor to a displayable name for the admin audit screen.
     * Returns null when the record has since been deleted.
     */
    public function actorName(): ?string
    {
        if ($this->actor_type === self::ACTOR_GUEST || !$this->actor_id) {
            return __('Guest');
        }

        $model = match ($this->actor_type) {
            self::ACTOR_STUDENT => Student::find($this->actor_id),
            self::ACTOR_APPLICANT => Applicant::find($this->actor_id),
            self::ACTOR_USER => \App\User::find($this->actor_id),
            default => null,
        };

        if (!$model) {
            return null;
        }

        return trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? '')) ?: ($model->email ?? null);
    }
}
