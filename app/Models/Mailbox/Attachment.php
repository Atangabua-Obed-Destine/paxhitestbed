<?php

namespace App\Models\Mailbox;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'mailbox_attachments';

    protected $fillable = ['message_id', 'path', 'original_name', 'mime', 'size'];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1) . ' MB' : max(1, round($this->size / 1024)) . ' KB';
    }
}
