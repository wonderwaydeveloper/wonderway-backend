<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TrendingService;
use Illuminate\Http\Request;

class TrendingController extends Controller
{
    private $trendingService;

    public function __construct(TrendingService $trendingService)
    {
        $this->trendingService = $trendingService;
    }

    /**
     * @OA\Get(
     *     path="/api/trending/hashtags",
     *     summary="Get trending hashtags",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="timeframe", in="query", @OA\Schema(type="integer", default=24)),
     *     @OA\Response(response=200, description="Trending hashtags")
     * )
     */
    public function hashtags(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'timeframe' => 'nullable|integer|min:1|max:168', // Max 7 days
        ]);

        $hashtags = $this->trendingService->getTrendingHashtags(
            $request->input('limit', 10),
            $request->input('timeframe', 24)
        );

        return response()->json([
            'data' => $hashtags,
            'meta' => [
                'limit' => $request->input('limit', 10),
                'timeframe_hours' => $request->input('timeframe', 24),
                'generated_at' => now()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/posts",
     *     summary="Get trending posts",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="timeframe", in="query", @OA\Schema(type="integer", default=24)),
     *     @OA\Response(response=200, description="Trending posts")
     * )
     */
    public function posts(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'timeframe' => 'nullable|integer|min:1|max:168',
        ]);

        $posts = $this->trendingService->getTrendingPosts(
            $request->input('limit', 20),
            $request->input('timeframe', 24)
        );

        return response()->json([
            'data' => $posts,
            'meta' => [
                'limit' => $request->input('limit', 20),
                'timeframe_hours' => $request->input('timeframe', 24),
                'generated_at' => now()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/users",
     *     summary="Get trending users",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="timeframe", in="query", @OA\Schema(type="integer", default=168)),
     *     @OA\Response(response=200, description="Trending users")
     * )
     */
    public function users(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'timeframe' => 'nullable|integer|min:1|max:720', // Max 30 days
        ]);

        $users = $this->trendingService->getTrendingUsers(
            $request->input('limit', 10),
            $request->input('timeframe', 168)
        );

        return response()->json([
            'data' => $users,
            'meta' => [
                'limit' => $request->input('limit', 10),
                'timeframe_hours' => $request->input('timeframe', 168),
                'generated_at' => now()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/personalized",
     *     summary="Get personalized trending content",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Personalized trending content")
     * )
     */
    public function personalized(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $content = $this->trendingService->getPersonalizedTrending(
            $request->user()->id,
            $request->input('limit', 10)
        );

        return response()->json([
            'data' => $content,
            'meta' => [
                'limit' => $request->input('limit', 10),
                'user_id' => $request->user()->id,
                'generated_at' => now()
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/velocity/{type}/{id}",
     *     summary="Get trend velocity for specific item",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="path", required=true, @OA\Schema(type="string", enum={"hashtag", "post"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="hours", in="query", @OA\Schema(type="integer", default=6)),
     *     @OA\Response(response=200, description="Trend velocity data")
     * )
     */
    public function velocity(Request $request, $type, $id)
    {
        $request->validate([
            'hours' => 'nullable|integer|min:1|max:24',
        ]);

        if (!in_array($type, ['hashtag', 'post'])) {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $velocity = $this->trendingService->getTrendVelocity(
            $type,
            $id,
            $request->input('hours', 6)
        );

        return response()->json([
            'type' => $type,
            'id' => $id,
            'velocity' => $velocity,
            'hours_analyzed' => $request->input('hours', 6),
            'interpretation' => $velocity > 0 ? 'accelerating' : ($velocity < 0 ? 'decelerating' : 'stable'),
            'generated_at' => now()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/all",
     *     summary="Get all trending content types",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="All trending content")
     * )
     */
    public function all(Request $request)
    {
        $hashtags = $this->trendingService->getTrendingHashtags(5);
        $posts = $this->trendingService->getTrendingPosts(10);
        $users = $this->trendingService->getTrendingUsers(5);

        return response()->json([
            'hashtags' => $hashtags,
            'posts' => $posts,
            'users' => $users,
            'generated_at' => now()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/trending/stats",
     *     summary="Get trending statistics",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Trending statistics")
     * )
     */
    public function stats()
    {
        $stats = $this->trendingService->getTrendingStats();

        return response()->json($stats);
    }

    /**
     * @OA\Post(
     *     path="/api/trending/refresh",
     *     summary="Refresh trending calculations",
     *     tags={"Trending"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Trending data refreshed")
     * )
     */
    public function refresh()
    {
        $result = $this->trendingService->updateTrendingScores();

        return response()->json([
            'message' => 'Trending data refreshed successfully',
            'result' => $result
        ]);
    }
}