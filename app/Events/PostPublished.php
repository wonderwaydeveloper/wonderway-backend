<?php

namespace App\Events;

use App\Models\Post;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostPublished implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Post $post
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('timeline'),
            new PrivateChannel('user.timeline.' . $this->post->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->post->id,
            'content' => $this->post->content,
            'user' => [
                'id' => $this->post->user->id,
                'name' => $this->post->user->name,
                'username' => $this->post->user->username,
                'avatar' => $this->post->user->avatar,
            ],
            'created_at' => $this->post->created_at,
            'likes_count' => $this->post->likes_count,
            'comments_count' => $this->post->comments_count,
        ];
    }
}
