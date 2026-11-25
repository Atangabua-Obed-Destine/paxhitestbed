<?php

namespace App\Models\Mailbox;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $table = 'mailbox_participants';

    protected $fillable = ['thread_id', 'member_type', 'member_id', 'last_read_at', 'last_notified_at', 'archived_at', 'deleted_at'];

    protected $casts = ['last_read_at' => 'datetime', 'last_notified_at' => 'datetime', 'archived_at' => 'datetime', 'deleted_at' => 'datetime'];

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }
}
