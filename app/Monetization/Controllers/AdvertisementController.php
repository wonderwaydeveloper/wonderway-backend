<?php

namespace App\Monetization\Controllers;

use App\Http\Controllers\Controller;
use App\Monetization\Models\Advertisement;
use App\Monetization\Services\AdvertisementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisementController extends Controller
{
    public function __construct(
        private AdvertisementService $advertisementService
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string|max:280',
            'media_url' => 'nullable|url',
            'budget' => 'required|numeric|min:10',
            'cost_per_click' => 'nullable|numeric|min:0.01',
            'cost_per_impression' => 'nullable|numeric|min:0.001',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'target_audience' => 'nullable|array',
            'targeting_criteria' => 'nullable|array',
        ]);

        $ad = $this->advertisementService->createAdvertisement([
            'advertiser_id' => auth()->id(),
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Advertisement created successfully',
            'data' => $ad,
        ], 201);
    }

    public function getTargetedAds(Request $request): JsonResponse
    {
        $ads = $this->advertisementService->getTargetedAds(
            auth()->user(),
            $request->get('limit', 3)
        );

        // Record impressions
        foreach ($ads as $ad) {
            $this->advertisementService->recordImpression($ad);
        }

        return response()->json([
            'data' => $ads->map(function ($ad) {
                return [
                    'id' => $ad->id,
                    'title' => $ad->title,
                    'content' => $ad->content,
                    'media_url' => $ad->media_url,
                    'advertiser' => $ad->advertiser->name,
                ];
            }),
        ]);
    }

    public function recordClick(Request $request, int $adId): JsonResponse
    {
        $ad = Advertisement::find($adId);

        if (! $ad) {
            return response()->json(['message' => 'Advertisement not found'], 404);
        }

        $this->advertisementService->recordClick($ad);

        return response()->json(['message' => 'Click recorded']);
    }

    public function getAnalytics(): JsonResponse
    {
        $analytics = $this->advertisementService->getAdvertiserAnalytics(auth()->id());

        return response()->json(['data' => $analytics]);
    }

    public function pause(int $adId): JsonResponse
    {
        $success = $this->advertisementService->pauseAdvertisement($adId);

        return response()->json([
            'message' => $success ? 'Advertisement paused' : 'Failed to pause advertisement',
        ]);
    }

    public function resume(int $adId): JsonResponse
    {
        $success = $this->advertisementService->resumeAdvertisement($adId);

        return response()->json([
            'message' => $success ? 'Advertisement resumed' : 'Failed to resume advertisement',
        ]);
    }
}
