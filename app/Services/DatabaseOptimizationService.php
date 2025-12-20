<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabaseOptimizationService
{
    public function optimizeTimeline($userId, $limit = 20)
    {
        $cacheKey = "timeline:optimized:{$userId}:page:" . request('page', 1);
        
        return Cache::remember($cacheKey, 300, function () use ($userId, $limit) {
            return DB::select("
                SELECT p.*, u.name, u.username, u.avatar,
                       p.likes_count, p.comments_count,
                       EXISTS(SELECT 1 FROM likes l WHERE l.likeable_id = p.id AND l.user_id = ?) as is_liked
                FROM posts p
                INNER JOIN users u ON p.user_id = u.id
                INNER JOIN follows f ON f.following_id = p.user_id
                WHERE f.follower_id = ? 
                   OR p.user_id = ?
                   AND p.published_at IS NOT NULL
                ORDER BY p.created_at DESC
                LIMIT ?
            ", [$userId, $userId, $userId, $limit]);
        });
    }

    public function getPopularPosts($hours = 24, $limit = 50)
    {
        $cacheKey = "posts:popular:{$hours}h";
        
        return Cache::remember($cacheKey, 600, function () use ($hours, $limit) {
            return DB::select("
                SELECT p.*, u.name, u.username, u.avatar,
                       (p.likes_count * 2 + p.comments_count * 3) as engagement_score
                FROM posts p
                INNER JOIN users u ON p.user_id = u.id
                WHERE p.created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
                  AND p.published_at IS NOT NULL
                ORDER BY engagement_score DESC
                LIMIT ?
            ", [$hours, $limit]);
        });
    }

    public function getUserStats($userId)
    {
        $cacheKey = "user:stats:{$userId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId) {
            return DB::selectOne("
                SELECT 
                    (SELECT COUNT(*) FROM posts WHERE user_id = ?) as posts_count,
                    (SELECT COUNT(*) FROM follows WHERE following_id = ?) as followers_count,
                    (SELECT COUNT(*) FROM follows WHERE follower_id = ?) as following_count,
                    (SELECT SUM(likes_count) FROM posts WHERE user_id = ?) as total_likes
            ", [$userId, $userId, $userId, $userId]);
        });
    }

    public function clearUserCache($userId)
    {
        $patterns = [
            "timeline:optimized:{$userId}:*",
            "user:stats:{$userId}",
            "posts:user:{$userId}:*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}