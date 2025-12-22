<?php

namespace App\Events;

use App\Models\Post;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostInteraction implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Post $post,
        public string $type, // 'like', 'comment', 'repost'
        public User $user,
        public array $data = []
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('post.' . $this->post->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->post->id,
            'type' => $this->type,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar' => $this->user->avatar,
            ],
            'likes_count' => $this->post->likes_count,
            'comments_count' => $this->post->comments_count,
            'data' => $this->data,
            'timestamp' => now(),
        ];
    }
}
