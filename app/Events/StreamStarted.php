<?php

namespace App\Events;

use App\Models\Stream;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamStarted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Stream $stream;

    public function __construct(Stream $stream)
    {
        $this->stream = $stream;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('streams'),
            new Channel('user.' . $this->stream->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'stream' => [
                'id' => $this->stream->id,
                'title' => $this->stream->title,
                'user' => $this->stream->user->only(['id', 'name', 'username']),
                'status' => $this->stream->status,
                'started_at' => $this->stream->started_at,
            ],
        ];
    }
}
