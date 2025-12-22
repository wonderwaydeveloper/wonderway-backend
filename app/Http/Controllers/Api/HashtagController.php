<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hashtag;
use App\Services\TrendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HashtagController extends Controller
{
    private $trendingService;

    public function __construct(TrendingService $trendingService)
    {
        $this->trendingService = $trendingService;
    }

    /**
     * @OA\Get(
     *     path="/api/hashtags/trending",
     *     summary="Get trending hashtags (legacy endpoint)",
     *     tags={"Hashtags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Trending hashtags")
     * )
     */
    public function trending()
    {
        // Use new trending service for better algorithm
        $hashtags = $this->trendingService->getTrendingHashtags(10, 24);

        return response()->json($hashtags);
    }

    /**
     * @OA\Get(
     *     path="/api/hashtags/{hashtag:slug}",
     *     summary="Get hashtag details and posts",
     *     tags={"Hashtags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="hashtag", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Hashtag details")
     * )
     */
    public function show(Hashtag $hashtag)
    {
        $cacheKey = "hashtag:{$hashtag->id}:posts:page:" . request('page', 1);

        $posts = Cache::remember($cacheKey, 1800, function () use ($hashtag) {
            return $hashtag->posts()
                ->published()
                ->whereNull('thread_id') // Only main posts
                ->with([
                    'user:id,name,username,avatar',
                    'hashtags:id,name,slug',
                    'quotedPost.user:id,name,username,avatar',
                ])
                ->withCount('likes', 'comments', 'quotes')
                ->latest('published_at')
                ->paginate(20);
        });

        // Get hashtag velocity
        $velocity = $this->trendingService->getTrendVelocity('hashtag', $hashtag->id, 6);

        return response()->json([
            'hashtag' => $hashtag,
            'posts' => $posts,
            'trending_info' => [
                'velocity' => $velocity,
                'is_trending' => $velocity > 0,
                'trend_direction' => $velocity > 0 ? 'up' : ($velocity < 0 ? 'down' : 'stable'),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/hashtags/search",
     *     summary="Search hashtags",
     *     tags={"Hashtags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="q", in="query", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Hashtag search results")
     * )
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
        ]);

        $query = $request->input('q');
        $cacheKey = "hashtag:search:" . md5($query);

        $hashtags = Cache::remember($cacheKey, 900, function () use ($query) {
            return Hashtag::where('name', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%")
                ->orderBy('posts_count', 'desc')
                ->limit(20)
                ->get(['id', 'name', 'slug', 'posts_count']);
        });

        return response()->json($hashtags);
    }

    /**
     * @OA\Get(
     *     path="/api/hashtags/suggestions",
     *     summary="Get hashtag suggestions for user",
     *     tags={"Hashtags"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Hashtag suggestions")
     * )
     */
    public function suggestions(Request $request)
    {
        $userId = $request->user()->id;
        $cacheKey = "hashtag:suggestions:{$userId}";

        $suggestions = Cache::remember($cacheKey, 3600, function () use ($userId) {
            // Get hashtags from user's recent posts
            $userHashtags = \DB::table('hashtag_post')
                ->join('posts', 'hashtag_post.post_id', '=', 'posts.id')
                ->where('posts.user_id', $userId)
                ->where('posts.published_at', '>=', now()->subDays(30))
                ->pluck('hashtag_post.hashtag_id')
                ->unique();

            // Get related trending hashtags
            return Hashtag::whereNotIn('id', $userHashtags)
                ->where('posts_count', '>', 10)
                ->orderBy('posts_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'slug', 'posts_count']);
        });

        return response()->json($suggestions);
    }
}
