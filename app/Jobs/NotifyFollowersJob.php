<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFollowersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private Post $post
    ) {
    }

    public function handle(): void
    {
        $followers = $this->post->user->followers()->pluck('users.id');

        foreach ($followers->chunk(100) as $chunk) {
            foreach ($chunk as $followerId) {
                Notification::create([
                    'user_id' => $followerId,
                    'type' => 'new_post',
                    'data' => [
                        'post_id' => $this->post->id,
                        'user_id' => $this->post->user_id,
                        'user_name' => $this->post->user->name,
                        'content' => substr($this->post->content, 0, 100),
                    ],
                ]);
            }
        }
    }
}
