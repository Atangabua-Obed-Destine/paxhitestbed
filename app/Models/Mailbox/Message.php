<?php

namespace App\Models\Mailbox;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'mailbox_messages';

    protected $fillable = ['thread_id', 'sender_type', 'sender_id', 'body'];

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'message_id');
    }
}
