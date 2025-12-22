<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $metrics = $this->analyticsService->getDashboardMetrics($request->user());

        return response()->json([
            'dashboard' => $metrics,
        ]);
    }

    public function userAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d',
        ]);

        $analytics = $this->analyticsService->getUserAnalytics(
            $request->user(),
            $request->get('period', '30d')
        );

        return response()->json([
            'analytics' => $analytics,
        ]);
    }

    public function postAnalytics(Request $request, Post $post): JsonResponse
    {
        // Only allow post owner to view analytics
        if ($post->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'period' => 'nullable|in:7d,30d,90d',
        ]);

        $analytics = $this->analyticsService->getPostAnalytics(
            $post->id,
            $request->get('period', '7d')
        );

        return response()->json([
            'post_analytics' => $analytics,
        ]);
    }

    public function trackEvent(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|in:post_view,post_like,post_comment,profile_view',
            'entity_type' => 'required|string|in:post,user',
            'entity_id' => 'required|integer',
            'metadata' => 'nullable|array',
        ]);

        \App\Models\AnalyticsEvent::track(
            $request->event_type,
            $request->entity_type,
            $request->entity_id,
            $request->user()?->id,
            $request->get('metadata', [])
        );

        return response()->json([
            'message' => 'Event tracked successfully',
        ]);
    }
}