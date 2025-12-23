<?php

namespace App\Repositories;

use App\Contracts\Repositories\PostRepositoryInterface;
use App\Models\Post;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostRepository implements PostRepositoryInterface
{
    public function create(array $data): Post
    {
        $post = Post::create($data);
        $this->clearUserCache($post->user_id);

        return $post;
    }

    public function findById(int $id): ?Post
    {
        return Cache::remember("post:{$id}", 300, function () use ($id) {
            return Post::with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
            ])->find($id);
        });
    }

    public function findWithRelations(int $id, array $relations = []): ?Post
    {
        return Post::with($relations)->find($id);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);
        Cache::forget("post:{$post->id}");
        $this->clearUserCache($post->user_id);

        return $post->fresh();
    }

    public function delete(Post $post): bool
    {
        Cache::forget("post:{$post->id}");
        $this->clearUserCache($post->user_id);

        return $post->delete();
    }

    public function getPublicPosts(int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return Post::published()
            ->with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
                'poll.options',
                'quotedPost.user:id,name,username,avatar',
            ])
            ->withCount(['likes', 'comments', 'quotes'])
            ->whereNull('thread_id')
            ->latest('published_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getTimelinePosts(int $userId, int $limit = 20): Collection
    {
        $cacheKey = "timeline:{$userId}:{$limit}";

        return Cache::remember($cacheKey, 300, function () use ($userId, $limit) {
            $followingIds = $this->getFollowingIds($userId);

            return Post::forTimeline()
                ->select(['id', 'user_id', 'content', 'created_at', 'likes_count', 'comments_count', 'image', 'gif_url', 'quoted_post_id'])
                ->whereIn('user_id', $followingIds)
                ->whereNull('thread_id')
                ->limit($limit)
                ->get();
        });
    }

    public function getUserDrafts(int $userId): LengthAwarePaginator
    {
        return Post::where('user_id', $userId)
            ->drafts()
            ->with(['hashtags:id,name,slug'])
            ->latest()
            ->paginate(20);
    }

    public function getPostQuotes(int $postId): LengthAwarePaginator
    {
        return Post::where('quoted_post_id', $postId)
            ->with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
            ])
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate(20);
    }

    public function getUserPosts(int $userId, int $limit = 20): Collection
    {
        return Post::where('user_id', $userId)
            ->published()
            ->with([
                'hashtags:id,name,slug',
                'quotedPost.user:id,name,username,avatar',
            ])
            ->withCount(['likes', 'comments', 'quotes'])
            ->whereNull('thread_id')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public function searchPosts(string $query, int $limit = 20): Collection
    {
        // Sanitize search query to prevent SQL injection
        $sanitizedQuery = $this->sanitizeSearchQuery($query);
        
        return Post::published()
            ->where('content', 'LIKE', "%{$sanitizedQuery}%")
            ->with([
                'user:id,name,username,avatar',
                'hashtags:id,name,slug',
            ])
            ->withCount(['likes', 'comments'])
            ->whereNull('thread_id')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function getFollowingIds(int $userId): array
    {
        return Cache::remember("following:{$userId}", 600, function () use ($userId) {
            return DB::table('follows')
                ->where('follower_id', $userId)
                ->pluck('following_id')
                ->push($userId)
                ->toArray();
        });
    }

    private function clearUserCache(int $userId): void
    {
        Cache::forget("timeline:{$userId}:20");
        Cache::forget("following:{$userId}");
    }

    /**
     * Sanitize search query to prevent SQL injection
     */
    private function sanitizeSearchQuery(string $query): string
    {
        // Remove dangerous characters
        $query = preg_replace('/[%_\\]/', '\\$0', $query);
        
        // Remove null bytes
        $query = str_replace(chr(0), '', $query);
        
        // Limit length
        $query = substr($query, 0, 100);
        
        return trim($query);
    }
}
