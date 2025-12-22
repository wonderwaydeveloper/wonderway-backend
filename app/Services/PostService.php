<?php

namespace App\Services;

use App\Contracts\PostRepositoryInterface;
use App\Events\PostInteraction;
use App\Events\PostPublished;
use App\Jobs\ProcessPostJob;
use App\Models\Post;
use App\Models\User;
use App\Notifications\MentionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

/**
 * Post Service Class
 *
 * Handles all post-related business logic including creation, updates,
 * likes, timeline management, and spam detection.
 *
 * @package App\Services
 * @author WonderWay Team
 * @version 1.0.0
 */
class PostService
{
    /**
     * PostService constructor.
     *
     * @param PostRepositoryInterface $postRepository Post repository for data access
     * @param SpamDetectionService $spamDetectionService Service for spam detection
     * @param DatabaseOptimizationService $databaseOptimizationService Service for database optimization
     * @param CacheOptimizationService $cacheService Service for cache optimization
     */
    public function __construct(
        private PostRepositoryInterface $postRepository,
        private SpamDetectionService $spamDetectionService,
        private DatabaseOptimizationService $databaseOptimizationService,
        private CacheOptimizationService $cacheService
    ) {
    }

    /**
     * Get public posts with caching
     *
     * @param int $page Page number for pagination
     * @return LengthAwarePaginator Paginated posts
     */
    public function getPublicPosts(int $page = 1): LengthAwarePaginator
    {
        $cacheKey = "posts:public:page:{$page}";

        return cache()->remember($cacheKey, 600, function () use ($page) {
            return $this->postRepository->getPublicPosts($page);
        });
    }

    /**
     * Create a new post
     *
     * @param array $data Post data including content, settings, etc.
     * @param User $user User creating the post
     * @param UploadedFile|null $image Optional image attachment
     * @param UploadedFile|null $video Optional video attachment
     * @return Post Created post with relations
     * @throws \Exception When post is detected as spam
     */
    public function createPost(array $data, User $user, ?UploadedFile $image = null, ?UploadedFile $video = null): Post
    {
        $postData = [
            'user_id' => $user->id,
            'content' => $data['content'],
            'reply_settings' => $data['reply_settings'] ?? 'everyone',
            'quoted_post_id' => $data['quoted_post_id'] ?? null,
            'gif_url' => $data['gif_url'] ?? null,
        ];

        // Handle image upload
        if ($image) {
            $postData['image'] = $image->store('posts', 'public');
        }

        // Handle video upload
        if ($video) {
            // Video will be processed asynchronously
            $postData['video'] = 'processing';
        }

        // Handle draft status
        $isDraft = $data['is_draft'] ?? false;
        $postData['is_draft'] = $isDraft;
        $postData['published_at'] = $isDraft ? null : now();

        $post = $this->postRepository->create($postData);

        // Handle video upload after post creation
        if ($video) {
            app(\App\Services\VideoUploadService::class)->uploadVideo($video, $post);
        }

        // Process hashtags and mentions
        $this->processPostContent($post);

        // Handle spam detection for published posts
        if (! $isDraft) {
            $this->handleSpamDetection($post);
        }

        // Process post asynchronously
        $this->processPostAsync($post, $isDraft);

        // Broadcast if published
        if (! $isDraft) {
            broadcast(new PostPublished($post->load('user:id,name,username,avatar')));
        }

        return $post->load('user:id,name,username,avatar', 'hashtags');
    }

    /**
     * Get post with full relations
     */
    public function getPostWithRelations(Post $post): array
    {
        $post = $this->postRepository->findWithRelations($post->id, [
            'user:id,name,username,avatar',
            'comments.user:id,name,username,avatar',
            'hashtags',
            'quotedPost.user:id,name,username,avatar',
            'threadPosts.user:id,name,username,avatar',
        ])->loadCount('likes', 'comments', 'quotes');

        $response = $post->toArray();

        if ($post->threadPosts()->exists()) {
            $response['thread_info'] = [
                'total_posts' => $post->threadPosts->count() + 1,
                'is_main_thread' => true,
            ];
        }

        return $response;
    }

    /**
     * Delete post and cleanup
     */
    public function deletePost(Post $post): void
    {
        if ($post->image) {
            Storage::disk('public')->delete($post->image);
        }

        $post->delete();
    }

    /**
     * Toggle like on post
     */
    public function toggleLike(Post $post, User $user): array
    {
        if ($post->isLikedBy($user->id)) {
            $post->likes()->where('user_id', $user->id)->delete();
            if ($post->likes_count > 0) {
                $post->decrement('likes_count');
            }
            $liked = false;
        } else {
            $post->likes()->create(['user_id' => $user->id]);
            $post->increment('likes_count');
            $liked = true;

            event(new \App\Events\PostLiked($post, $user));
        }

        // Broadcast real-time interaction
        broadcast(new PostInteraction($post, 'like', $user, ['liked' => $liked]));

        return ['liked' => $liked, 'likes_count' => $post->likes_count];
    }

    /**
     * Get user timeline
     */
    public function getUserTimeline(User $user, int $limit = 20): array
    {
        // Use optimized cached timeline
        $posts = $this->cacheService->getOptimizedTimeline($user->id, $limit);

        return [
            'data' => $posts,
            'optimized' => true,
            'cached' => true,
        ];
    }

    /**
     * Get user drafts
     */
    public function getUserDrafts(User $user): LengthAwarePaginator
    {
        return $this->postRepository->getUserDrafts($user->id);
    }

    /**
     * Publish draft post
     */
    public function publishPost(Post $post): Post
    {
        $post->update([
            'is_draft' => false,
            'published_at' => now(),
        ]);

        return $post;
    }

    /**
     * Create quote post
     */
    public function createQuotePost(array $data, User $user, Post $originalPost): Post
    {
        $quotePost = Post::create([
            'user_id' => $user->id,
            'content' => $data['content'],
            'quoted_post_id' => $originalPost->id,
            'is_draft' => false,
            'published_at' => now(),
        ]);

        $this->processPostContent($quotePost);

        broadcast(new PostPublished(
            $quotePost->load('user:id,name,username,avatar', 'quotedPost.user:id,name,username,avatar')
        ));

        return $quotePost->load('user:id,name,username,avatar', 'quotedPost.user:id,name,username,avatar', 'hashtags');
    }

    /**
     * Get post quotes
     */
    public function getPostQuotes(Post $post): LengthAwarePaginator
    {
        return $this->postRepository->getPostQuotes($post->id);
    }

    /**
     * Update post content
     */
    public function updatePost(Post $post, array $data): Post
    {
        $post->editPost(
            $data['content'],
            $data['edit_reason'] ?? null
        );

        $post->syncHashtags();

        return $post->load('user:id,name,username,avatar', 'hashtags', 'edits');
    }

    /**
     * Get post edit history
     */
    public function getEditHistory(Post $post): array
    {
        $edits = $post->edits()->with('post:id,content')->get();

        return [
            'current_content' => $post->content,
            'edit_history' => $edits,
        ];
    }

    /**
     * Process post content (hashtags and mentions)
     */
    private function processPostContent(Post $post): void
    {
        $post->syncHashtags();
        $mentionedUsers = $post->processMentions($post->content);

        foreach ($mentionedUsers as $mentionedUser) {
            $mentionedUser->notify(new MentionNotification(auth()->user(), $post));
        }
    }

    /**
     * Handle spam detection
     */
    private function handleSpamDetection(Post $post): void
    {
        $spamResult = $this->spamDetectionService->checkPost($post);

        if ($spamResult['is_spam']) {
            $post->delete();

            $errorType = 'SPAM_DETECTED';
            if (in_array('Too many links detected (3 links)', $spamResult['reasons'])) {
                $errorType = 'TOO_MANY_LINKS';
            }

            throw new \Exception('پست شما به دلیل مشکوک بودن تأیید نشد', 422);
        }
    }

    /**
     * Process post asynchronously
     */
    private function processPostAsync(Post $post, bool $isDraft): void
    {
        if (! $isDraft && ! app()->environment('testing')) {
            dispatch(new ProcessPostJob($post))->onQueue('high');
        }
    }
}
