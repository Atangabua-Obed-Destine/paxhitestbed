<?php

namespace App\Models\Mailbox;

use Illuminate\Database\Eloquent\Model;

class Thread extends Model
{
    protected $table = 'mailbox_threads';

    protected $fillable = ['subject', 'broadcast_id', 'office_id', 'created_by_type', 'created_by_id', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    public function participants()
    {
        return $this->hasMany(Participant::class, 'thread_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'thread_id')->orderBy('created_at')->orderBy('id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'thread_id')->latestOfMany();
    }

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    /** The participant row for one member, or null when they are not in it. */
    public function participantFor(array $member): ?Participant
    {
        return $this->participants->first(fn ($p) => $p->member_type === $member[0] && (int) $p->member_id === (int) $member[1] && !$p->deleted_at);
    }
}
