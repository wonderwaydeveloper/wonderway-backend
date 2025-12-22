<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\ScheduledPost;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';
    protected $description = 'Publish scheduled posts that are ready';

    public function handle()
    {
        $scheduledPosts = ScheduledPost::where('scheduled_at', '<=', now())
            ->where('published', false)
            ->get();

        foreach ($scheduledPosts as $scheduledPost) {
            try {
                $post = Post::create([
                    'user_id' => $scheduledPost->user_id,
                    'content' => $scheduledPost->content,
                    'media_urls' => $scheduledPost->media_urls,
                    'reply_settings' => $scheduledPost->reply_settings,
                ]);

                $scheduledPost->update(['published' => true, 'post_id' => $post->id]);

                Log::info('Scheduled post published', ['post_id' => $post->id]);
                $this->info("Published post: {$post->id}");
            } catch (\Exception $e) {
                Log::error('Failed to publish scheduled post', ['error' => $e->getMessage()]);
                $this->error("Failed to publish scheduled post: {$e->getMessage()}");
            }
        }

        $this->info('Scheduled posts published successfully');
    }
}
