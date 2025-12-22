<?php

namespace App\Monetization\Services;

use App\Models\Post;
use App\Models\User;
use App\Monetization\Models\CreatorFund;
use Illuminate\Support\Collection;

class CreatorFundService
{
    public function calculateMonthlyEarnings(User $creator, int $month, int $year): float
    {
        $posts = Post::where('user_id', $creator->id)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->get();

        $totalViews = $posts->sum('views_count');
        $totalLikes = $posts->sum('likes_count');
        $totalComments = $posts->sum('comments_count');
        $totalReposts = $posts->sum('reposts_count');

        $totalEngagement = $totalLikes + $totalComments + $totalReposts;
        $qualityScore = $this->calculateQualityScore($creator, $posts);

        $fund = CreatorFund::updateOrCreate(
            [
                'creator_id' => $creator->id,
                'month' => $month,
                'year' => $year,
            ],
            [
                'total_views' => $totalViews,
                'total_engagement' => $totalEngagement,
                'quality_score' => $qualityScore,
                'metrics' => [
                    'posts_count' => $posts->count(),
                    'avg_engagement_rate' => $totalViews > 0 ? ($totalEngagement / $totalViews) * 100 : 0,
                    'follower_growth' => $this->getFollowerGrowth($creator, $month, $year),
                ],
            ]
        );

        $earnings = $fund->calculateEarnings();
        $fund->update(['earnings' => $earnings]);

        return $earnings;
    }

    public function processPayments(int $month, int $year): array
    {
        $eligibleFunds = CreatorFund::where('month', $month)
            ->where('year', $year)
            ->where('status', 'pending')
            ->whereHas('creator', function ($query) {
                $query->where('is_verified', true);
            })
            ->get()
            ->filter(fn ($fund) => $fund->isEligible());

        $processed = [];
        foreach ($eligibleFunds as $fund) {
            if ($this->processPayment($fund)) {
                $processed[] = $fund;
            }
        }

        return $processed;
    }

    private function processPayment(CreatorFund $fund): bool
    {
        // Integration with payment gateway would go here
        // For now, we'll simulate successful payment

        $fund->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Send notification to creator
        $fund->creator->notify(new \App\Notifications\CreatorFundPayment($fund));

        return true;
    }

    private function calculateQualityScore(User $creator, Collection $posts): float
    {
        $baseScore = 70;

        // Engagement rate bonus
        $totalViews = $posts->sum('views_count');
        $totalEngagement = $posts->sum('likes_count') + $posts->sum('comments_count');
        $engagementRate = $totalViews > 0 ? ($totalEngagement / $totalViews) * 100 : 0;

        if ($engagementRate > 5) {
            $baseScore += 10;
        }
        if ($engagementRate > 10) {
            $baseScore += 10;
        }

        // Consistency bonus
        $postsCount = $posts->count();
        if ($postsCount >= 10) {
            $baseScore += 5;
        }
        if ($postsCount >= 20) {
            $baseScore += 5;
        }

        // Follower count bonus
        $followersCount = $creator->followers()->count();
        if ($followersCount > 10000) {
            $baseScore += 5;
        }
        if ($followersCount > 100000) {
            $baseScore += 5;
        }

        return min($baseScore, 100);
    }

    private function getFollowerGrowth(User $creator, int $month, int $year): int
    {
        // This would require tracking follower history
        // For now, return a placeholder
        return 0;
    }

    public function getCreatorAnalytics(User $creator): array
    {
        $funds = CreatorFund::where('creator_id', $creator->id)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return [
            'total_earnings' => $funds->sum('earnings'),
            'last_month_earnings' => $funds->first()?->earnings ?? 0,
            'average_quality_score' => $funds->avg('quality_score'),
            'total_views' => $funds->sum('total_views'),
            'total_engagement' => $funds->sum('total_engagement'),
            'months_active' => $funds->count(),
            'payment_history' => $funds->where('status', 'paid')->values(),
        ];
    }
}
