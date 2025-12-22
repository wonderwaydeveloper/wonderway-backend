<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrendingService
{
    public const TRENDING_CACHE_TTL = 900; // 15 minutes
    public const HASHTAG_TREND_THRESHOLD = 5; // Minimum posts in last 24h
    public const POST_TREND_THRESHOLD = 10; // Minimum engagement score
    public const USER_TREND_THRESHOLD = 100; // Minimum followers for trending users

    /**
     * Calculate trending hashtags with time decay
     */
    public function getTrendingHashtags($limit = 10, $timeframe = 24)
    {
        $cacheKey = "trending_hashtags_{$limit}_{$timeframe}";

        return Cache::remember($cacheKey, self::TRENDING_CACHE_TTL, function () use ($limit, $timeframe) {
            $cutoffTime = Carbon::now()->subHours($timeframe);

            return DB::table('hashtags')
                ->select([
                    'hashtags.*',
                    DB::raw('COUNT(hashtag_post.post_id) as recent_posts_count'),
                    DB::raw('SUM(posts.likes_count + posts.comments_count * 2) as engagement_score'),
                    DB::raw('(COUNT(hashtag_post.post_id) * 0.6 + SUM(posts.likes_count + posts.comments_count * 2) * 0.4) as trend_score'),
                ])
                ->join('hashtag_post', 'hashtags.id', '=', 'hashtag_post.hashtag_id')
                ->join('posts', 'hashtag_post.post_id', '=', 'posts.id')
                ->where('posts.published_at', '>=', $cutoffTime)
                ->where('posts.is_draft', false)
                ->groupBy('hashtags.id')
                ->having('recent_posts_count', '>=', self::HASHTAG_TREND_THRESHOLD)
                ->orderBy('trend_score', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Calculate trending posts with engagement scoring
     */
    public function getTrendingPosts($limit = 20, $timeframe = 24)
    {
        $cacheKey = "trending_posts_{$limit}_{$timeframe}";

        return Cache::remember($cacheKey, self::TRENDING_CACHE_TTL, function () use ($limit, $timeframe) {
            $cutoffTime = Carbon::now()->subHours($timeframe);

            return Post::select([
                'posts.*',
                DB::raw('(
                    likes_count * 1.0 + 
                    comments_count * 2.0 + 
                    (SELECT COUNT(*) FROM posts as reposts WHERE reposts.quoted_post_id = posts.id) * 1.5 +
                    (TIMESTAMPDIFF(HOUR, posts.published_at, NOW()) * -0.1)
                ) as engagement_score'),
            ])
            ->published()
            ->where('published_at', '>=', $cutoffTime)
            ->whereNull('thread_id') // Only main posts
            ->with(['user:id,name,username,avatar', 'hashtags:id,name,slug'])
            ->withCount(['likes', 'comments', 'quotes'])
            ->having('engagement_score', '>=', self::POST_TREND_THRESHOLD)
            ->orderBy('engagement_score', 'desc')
            ->limit($limit)
            ->get();
        });
    }

    /**
     * Calculate trending users based on recent activity
     */
    public function getTrendingUsers($limit = 10, $timeframe = 168) // 7 days
    {
        $cacheKey = "trending_users_{$limit}_{$timeframe}";

        return Cache::remember($cacheKey, self::TRENDING_CACHE_TTL, function () use ($limit, $timeframe) {
            $cutoffTime = Carbon::now()->subHours($timeframe);

            return User::select([
                'users.*',
                DB::raw('COUNT(DISTINCT posts.id) as recent_posts_count'),
                DB::raw('SUM(posts.likes_count + posts.comments_count) as total_engagement'),
                DB::raw('COUNT(DISTINCT follows.follower_id) as followers_gained'),
                DB::raw('(
                    COUNT(DISTINCT posts.id) * 2.0 +
                    SUM(posts.likes_count + posts.comments_count) * 0.1 +
                    COUNT(DISTINCT follows.follower_id) * 1.0
                ) as trend_score'),
            ])
            ->leftJoin('posts', function ($join) use ($cutoffTime) {
                $join->on('users.id', '=', 'posts.user_id')
                     ->where('posts.published_at', '>=', $cutoffTime)
                     ->where('posts.is_draft', false);
            })
            ->leftJoin('follows', function ($join) use ($cutoffTime) {
                $join->on('users.id', '=', 'follows.following_id')
                     ->where('follows.created_at', '>=', $cutoffTime);
            })
            ->groupBy('users.id')
            ->having('trend_score', '>', 0)
            ->orderBy('trend_score', 'desc')
            ->limit($limit)
            ->get();
        });
    }

    /**
     * Get personalized trending based on user's interests
     */
    public function getPersonalizedTrending($userId, $limit = 10)
    {
        $cacheKey = "personalized_trending_{$userId}_{$limit}";

        return Cache::remember($cacheKey, self::TRENDING_CACHE_TTL, function () use ($userId, $limit) {
            // Get user's followed hashtags and users
            $userHashtags = DB::table('hashtag_post')
                ->join('posts', 'hashtag_post.post_id', '=', 'posts.id')
                ->where('posts.user_id', $userId)
                ->pluck('hashtag_post.hashtag_id')
                ->unique()
                ->take(20)
                ->toArray();

            $followedUsers = DB::table('follows')
                ->where('follower_id', $userId)
                ->pluck('following_id')
                ->toArray();

            // Simple fallback if no data
            if (empty($userHashtags) && empty($followedUsers)) {
                return Post::published()
                    ->where('published_at', '>=', Carbon::now()->subHours(48))
                    ->whereNull('thread_id')
                    ->with(['user:id,name,username,avatar', 'hashtags:id,name,slug'])
                    ->withCount(['likes', 'comments', 'quotes'])
                    ->orderBy('likes_count', 'desc')
                    ->limit($limit)
                    ->get();
            }

            // Build query with proper syntax
            $query = Post::select([
                'posts.*',
                DB::raw('(
                    likes_count * 1.0 + 
                    comments_count * 2.0 + 
                    CASE WHEN posts.user_id IN (' . (empty($followedUsers) ? '0' : implode(',', $followedUsers)) . ') THEN 5.0 ELSE 0 END
                ) as personalized_score'),
            ])
            ->published()
            ->where('published_at', '>=', Carbon::now()->subHours(48))
            ->whereNull('thread_id')
            ->with(['user:id,name,username,avatar', 'hashtags:id,name,slug'])
            ->withCount(['likes', 'comments', 'quotes']);

            // Add hashtag bonus if user has hashtag history
            if (! empty($userHashtags)) {
                $query->orWhereHas('hashtags', function ($q) use ($userHashtags) {
                    $q->whereIn('hashtags.id', $userHashtags);
                });
            }

            return $query->having('personalized_score', '>', 0)
                ->orderBy('personalized_score', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Calculate trend velocity (how fast something is trending)
     */
    public function getTrendVelocity($type, $id, $hours = 6)
    {
        $cacheKey = "trend_velocity_{$type}_{$id}_{$hours}";

        return Cache::remember($cacheKey, 300, function () use ($type, $id, $hours) {
            $intervals = [];

            for ($i = 0; $i < $hours; $i++) {
                $start = Carbon::now()->subHours($i + 1);
                $end = Carbon::now()->subHours($i);

                if ($type === 'hashtag') {
                    $count = DB::table('hashtag_post')
                        ->join('posts', 'hashtag_post.post_id', '=', 'posts.id')
                        ->where('hashtag_post.hashtag_id', $id)
                        ->where('posts.published_at', '>=', $start)
                        ->where('posts.published_at', '<', $end)
                        ->count();
                } elseif ($type === 'post') {
                    $count = DB::table('likes')
                        ->where('likeable_id', $id)
                        ->where('likeable_type', Post::class)
                        ->where('created_at', '>=', $start)
                        ->where('created_at', '<', $end)
                        ->count();
                }

                $intervals[] = $count;
            }

            // Calculate velocity (rate of change)
            $velocity = 0;
            for ($i = 1; $i < count($intervals); $i++) {
                $velocity += ($intervals[$i - 1] - $intervals[$i]);
            }

            return $velocity / ($hours - 1);
        });
    }

    /**
     * Update trending scores (called by scheduler)
     */
    public function updateTrendingScores()
    {
        // Clear trending caches
        $this->clearTrendingCaches();

        // Pre-calculate trending data
        $this->getTrendingHashtags();
        $this->getTrendingPosts();
        $this->getTrendingUsers();

        return [
            'hashtags_updated' => true,
            'posts_updated' => true,
            'users_updated' => true,
            'timestamp' => now(),
        ];
    }

    /**
     * Clear all trending caches
     */
    public function clearTrendingCaches()
    {
        $patterns = [
            'trending_hashtags_*',
            'trending_posts_*',
            'trending_users_*',
            'personalized_trending_*',
            'trend_velocity_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get trending statistics
     */
    public function getTrendingStats()
    {
        return [
            'total_trending_hashtags' => $this->getTrendingHashtags(100)->count(),
            'total_trending_posts' => $this->getTrendingPosts(100)->count(),
            'total_trending_users' => $this->getTrendingUsers(50)->count(),
            'cache_status' => [
                'hashtags_cached' => Cache::has('trending_hashtags_10_24'),
                'posts_cached' => Cache::has('trending_posts_20_24'),
                'users_cached' => Cache::has('trending_users_10_168'),
            ],
            'last_updated' => Cache::get('trending_last_updated', 'Never'),
        ];
    }
}
