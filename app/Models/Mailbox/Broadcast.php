<?php

namespace App\Models\Mailbox;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $table = 'mailbox_broadcasts';

    protected $fillable = ['sender_type', 'sender_id', 'subject', 'body', 'audience', 'recipient_count'];

    public function threads()
    {
        return $this->hasMany(Thread::class, 'broadcast_id');
    }
}
