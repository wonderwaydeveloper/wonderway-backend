<?php

namespace App\Events;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamViewerLeft implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public LiveStream $stream, public User $user)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('stream.' . $this->stream->id)];
    }

    public function broadcastWith(): array
    {
        return [
            'viewer' => $this->user->only(['id', 'name', 'username', 'avatar']),
            'viewer_count' => $this->stream->viewer_count,
        ];
    }
}
