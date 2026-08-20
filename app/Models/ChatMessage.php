<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single turn in a conversation, or a record of one tool invocation.
 */
class ChatMessage extends Model
{
    use HasFactory;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    public const STATUS_OK = 'ok';
    public const STATUS_DENIED = 'denied';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_name',
        'tool_args',
        'tool_result',
        'tool_status',
        'acting_user_id',
        'latency_ms',
        'error',
    ];

    protected $casts = [
        'tool_args' => 'array',
        'tool_result' => 'array',
        'latency_ms' => 'integer',
    ];

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }
}
