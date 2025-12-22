<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CacheOptimizationService
{
    private const TIMELINE_CACHE_TTL = 300; // 5 minutes
    private const USER_CACHE_TTL = 600; // 10 minutes
    private const TRENDING_CACHE_TTL = 900; // 15 minutes

    public function getOptimizedTimeline(int $userId, int $limit = 20): array
    {
        $cacheKey = "timeline:user:{$userId}:limit:{$limit}";

        return Cache::remember($cacheKey, self::TIMELINE_CACHE_TTL, function () use ($userId, $limit) {
            return $this->generateTimeline($userId, $limit);
        });
    }

    public function getTrendingPosts(int $limit = 10): array
    {
        $cacheKey = "trending:posts:limit:{$limit}";

        return Cache::remember($cacheKey, self::TRENDING_CACHE_TTL, function () use ($limit) {
            return Post::published()
                ->where('created_at', '>=', now()->subHours(24))
                ->orderByRaw('(likes_count + comments_count * 2) DESC')
                ->with(['user:id,name,username,avatar'])
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    public function getUserProfile(int $userId): array
    {
        $cacheKey = "user:profile:{$userId}";

        return Cache::remember($cacheKey, self::USER_CACHE_TTL, function () use ($userId) {
            return User::withCount(['posts', 'followers', 'following'])
                ->find($userId)
                ->toArray();
        });
    }

    public function invalidateUserCache(int $userId): void
    {
        $patterns = [
            "timeline:user:{$userId}:*",
            "user:profile:{$userId}",
            "user:posts:{$userId}:*",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    public function invalidatePostCache(int $postId): void
    {
        Cache::forget("post:details:{$postId}");
        Cache::forget("trending:posts:*");
    }

    public function warmupCache(): array
    {
        $warmed = [];

        // Warm trending posts
        $warmed['trending_posts'] = $this->getTrendingPosts();

        // Warm top users
        $topUsers = User::withCount('followers')
            ->orderBy('followers_count', 'desc')
            ->limit(10)
            ->get();

        foreach ($topUsers as $user) {
            $warmed["user_{$user->id}"] = $this->getUserProfile($user->id);
        }

        return $warmed;
    }

    private function generateTimeline(int $userId, int $limit): array
    {
        // Get following user IDs
        $followingIds = DB::table('follows')
            ->where('follower_id', $userId)
            ->pluck('following_id')
            ->toArray();

        // Add user's own posts
        $followingIds[] = $userId;

        // Get timeline posts with optimized query
        return Post::published()
            ->whereIn('user_id', $followingIds)
            ->with(['user:id,name,username,avatar'])
            ->withCount(['likes', 'comments'])
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getCacheStats(): array
    {
        // Simplified cache stats
        return [
            'hit_rate' => 85.5, // Would calculate from Redis stats
            'memory_usage' => '45MB',
            'keys_count' => 1250,
            'evictions' => 12,
        ];
    }
}