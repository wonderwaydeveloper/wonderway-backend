<?php

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class UpdateTimelineCacheJob implements ShouldQueue
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
        // Clear user's own timeline cache
        $this->clearUserTimeline($this->post->user_id);

        // Clear followers' timeline caches
        $followerIds = $this->post->user->followers()->pluck('users.id');

        foreach ($followerIds as $followerId) {
            $this->clearUserTimeline($followerId);
        }

        // Clear public posts cache
        for ($page = 1; $page <= 5; $page++) {
            Cache::forget("posts:public:page:{$page}");
        }
    }

    private function clearUserTimeline(int $userId): void
    {
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget("timeline:user:{$userId}:page:{$page}");
        }
    }
}
