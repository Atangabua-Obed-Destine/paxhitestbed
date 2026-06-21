<?php

namespace App\Events;

use App\Models\ClassSessionAlert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClassSessionAlertCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $formattedAlert;

    /**
     * Create a new event instance.
     *
     * @param ClassSessionAlert $alert
     * @return void
     */
    public function __construct(ClassSessionAlert $alert)
    {
        $this->alert = $alert;
        $this->formattedAlert = [
            'id' => $alert->id,
            'title' => $alert->title,
            'description' => $alert->description,
            'alert_type' => $alert->alert_type,
            'is_pinned' => $alert->is_pinned,
            'upvote_count' => $alert->upvote_count,
            'created_at' => $alert->created_at->diffForHumans(),
            'created_by' => [
                'id' => $alert->createdBy->id ?? null,
                'name' => $alert->createdBy->student->first_name . ' ' . $alert->createdBy->student->last_name ?? 'Unknown',
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
        return new PresenceChannel('class-session.' . $this->alert->class_session_id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'alert.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'alert' => $this->formattedAlert,
        ];
    }
}
