<?php

namespace Lyn\LaravelCasServer\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CasLogoutEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $session_id;

    /**
     * Create a new event instance.
     * @param string $session_id
     * @return void
     */
    public function __construct(string $session_id)
    {
        $this->session_id = $session_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }

    public function getSessionId()
    {
        return $this->session_id;
    }
}
