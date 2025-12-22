<?php

namespace App\Events;

use App\Models\Space;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpaceParticipantLeft implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $space;
    public $user;

    public function __construct(Space $space, User $user)
    {
        $this->space = $space;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('space.' . $this->space->id);
    }

    public function broadcastWith()
    {
        return [
            'user' => $this->user->only(['id', 'name', 'username', 'avatar']),
            'participants_count' => $this->space->current_participants,
        ];
    }
}
