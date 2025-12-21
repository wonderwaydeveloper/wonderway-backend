<?php

namespace App\Monetization\Controllers;

use App\Http\Controllers\Controller;
use App\Monetization\Services\CreatorFundService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CreatorFundController extends Controller
{
    public function __construct(
        private CreatorFundService $creatorFundService
    ) {}

    public function getAnalytics(): JsonResponse
    {
        $analytics = $this->creatorFundService->getCreatorAnalytics(auth()->user());

        return response()->json(['data' => $analytics]);
    }

    public function calculateEarnings(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024',
        ]);

        $earnings = $this->creatorFundService->calculateMonthlyEarnings(
            auth()->user(),
            $request->month,
            $request->year
        );

        return response()->json([
            'message' => 'Earnings calculated successfully',
            'data' => [
                'month' => $request->month,
                'year' => $request->year,
                'earnings' => $earnings
            ]
        ]);
    }

    public function getEarningsHistory(): JsonResponse
    {
        $history = auth()->user()->creatorFunds()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json(['data' => $history]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024',
        ]);

        $fund = auth()->user()->creatorFunds()
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        if (!$fund) {
            return response()->json(['message' => 'No earnings found for this period'], 404);
        }

        if (!$fund->isEligible()) {
            return response()->json(['message' => 'Not eligible for payout'], 400);
        }

        if ($fund->status !== 'pending') {
            return response()->json(['message' => 'Payout already processed'], 400);
        }

        $fund->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Payout request submitted successfully',
            'data' => $fund
        ]);
    }
}