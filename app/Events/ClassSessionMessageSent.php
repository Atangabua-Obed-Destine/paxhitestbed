<?php

namespace App\Events;

use App\Models\ClassSessionMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClassSessionMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $formattedMessage;

    /**
     * Create a new event instance.
     *
     * @param ClassSessionMessage $message
     * @return void
     */
    public function __construct(ClassSessionMessage $message)
    {
        $this->message = $message;
        $this->formattedMessage = [
            'id' => $message->id,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'is_pinned' => $message->is_pinned,
            'created_at' => $message->created_at->format('H:i'),
            'student' => [
                'id' => $message->studentEnroll->id ?? null,
                'name' => $message->studentEnroll->student->first_name . ' ' . $message->studentEnroll->student->last_name ?? 'Unknown',
                'photo' => $message->studentEnroll->student->photo ?? null,
            ],
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PresenceChannel('class-session.' . $this->message->class_session_id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'message' => $this->formattedMessage,
        ];
    }
}
