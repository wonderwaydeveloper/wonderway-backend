<?php

namespace App\Events;

use App\Models\LiveStream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveStream $stream)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('live-streams'),
            new Channel('user.' . $this->stream->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'stream' => $this->stream->load('user:id,name,username,avatar'),
            'message' => $this->stream->user->name . ' started streaming: ' . $this->stream->title,
        ];
    }
}