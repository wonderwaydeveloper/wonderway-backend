<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheManagementService
{
    public function warmupCache()
    {
        // Warm up trending hashtags
        $this->cacheTrendingHashtags();
        
        // Warm up popular posts
        $this->cachePopularPosts();
        
        // Warm up user suggestions
        $this->cacheUserSuggestions();
    }

    public function cacheTrendingHashtags()
    {
        $trending = \App\Models\Hashtag::withCount('posts')
            ->where('created_at', '>', now()->subDays(7))
            ->orderBy('posts_count', 'desc')
            ->limit(20)
            ->get();

        Cache::put('hashtags:trending', $trending, 1800); // 30 minutes
        return $trending;
    }

    public function cachePopularPosts()
    {
        $popular = \App\Models\Post::published()
            ->with('user:id,name,username,avatar')
            ->withCount('likes', 'comments')
            ->where('created_at', '>', now()->subHours(24))
            ->orderByRaw('(likes_count * 2 + comments_count * 3) DESC')
            ->limit(50)
            ->get();

        Cache::put('posts:popular:24h', $popular, 600); // 10 minutes
        return $popular;
    }

    public function cacheUserSuggestions()
    {
        $suggestions = \App\Models\User::select('id', 'name', 'username', 'avatar')
            ->withCount('followers')
            ->where('created_at', '>', now()->subDays(30))
            ->orderBy('followers_count', 'desc')
            ->limit(20)
            ->get();

        Cache::put('users:suggestions', $suggestions, 3600); // 1 hour
        return $suggestions;
    }

    public function invalidateUserCache($userId)
    {
        $keys = [
            "timeline:user:{$userId}:*",
            "user:stats:{$userId}",
            "user:profile:{$userId}",
            "posts:user:{$userId}:*"
        ];

        foreach ($keys as $pattern) {
            if (str_contains($pattern, '*')) {
                $this->deleteByPattern($pattern);
            } else {
                Cache::forget($pattern);
            }
        }
    }

    public function invalidatePostCache($postId)
    {
        Cache::forget("post:{$postId}");
        Cache::forget("post:comments:{$postId}");
        Cache::forget('posts:popular:24h');
        Cache::forget('posts:public:*');
    }

    private function deleteByPattern($pattern)
    {
        if (config('cache.default') === 'redis') {
            $keys = Redis::keys($pattern);
            if (!empty($keys)) {
                Redis::del($keys);
            }
        }
    }

    public function getCacheStats()
    {
        $stats = [
            'trending_hashtags' => Cache::has('hashtags:trending'),
            'popular_posts' => Cache::has('posts:popular:24h'),
            'user_suggestions' => Cache::has('users:suggestions'),
        ];

        if (config('cache.default') === 'redis') {
            $info = Redis::info();
            $stats['redis'] = [
                'used_memory' => $info['used_memory_human'] ?? 'N/A',
                'connected_clients' => $info['connected_clients'] ?? 'N/A',
                'keyspace_hits' => $info['keyspace_hits'] ?? 'N/A',
                'keyspace_misses' => $info['keyspace_misses'] ?? 'N/A',
            ];
        }

        return $stats;
    }
}