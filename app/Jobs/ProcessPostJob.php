<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\QueueManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPostJob implements ShouldQueue
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
        // Extract hashtags and mentions
        $this->post->syncHashtags();
        $this->post->processMentions($this->post->content);

        // Generate thumbnail if image exists
        if ($this->post->image) {
            dispatch(new GenerateThumbnailJob($this->post))->onQueue('low');
        }

        // Notify followers
        dispatch(new NotifyFollowersJob($this->post))->onQueue('high');

        // Update timeline cache
        dispatch(new UpdateTimelineCacheJob($this->post))->onQueue('default');

        // Increment processed count
        app(QueueManager::class)->incrementProcessedCount();
    }
}
