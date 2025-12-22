<?php

namespace App\Monetization\Services;

use App\Models\User;
use App\Monetization\Models\Advertisement;
use Illuminate\Support\Collection;

class AdvertisementService
{
    public function createAdvertisement(array $data): Advertisement
    {
        return Advertisement::create([
            'advertiser_id' => $data['advertiser_id'],
            'title' => $data['title'],
            'content' => $data['content'],
            'media_url' => $data['media_url'] ?? null,
            'target_audience' => $data['target_audience'] ?? [],
            'budget' => $data['budget'],
            'cost_per_click' => $data['cost_per_click'] ?? 0.10,
            'cost_per_impression' => $data['cost_per_impression'] ?? 0.01,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'pending',
            'targeting_criteria' => $data['targeting_criteria'] ?? [],
        ]);
    }

    public function getTargetedAds(User $user, int $limit = 3): Collection
    {
        return Advertisement::where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('total_spent', '<', 'budget')
            ->where(function ($query) use ($user) {
                $query->whereJsonContains('target_audience', $user->country)
                    ->orWhereJsonContains('target_audience', $user->age_group)
                    ->orWhereJsonContains('target_audience', 'all');
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    public function recordImpression(Advertisement $ad): void
    {
        $ad->increment('impressions_count');
        $cost = $ad->cost_per_impression;
        $ad->increment('total_spent', $cost);
    }

    public function recordClick(Advertisement $ad): void
    {
        $ad->increment('clicks_count');
        $cost = $ad->cost_per_click;
        $ad->increment('total_spent', $cost);
    }

    public function recordConversion(Advertisement $ad): void
    {
        $ad->increment('conversions_count');
    }

    public function getAdvertiserAnalytics(int $advertiserId): array
    {
        $ads = Advertisement::where('advertiser_id', $advertiserId)->get();

        return [
            'total_campaigns' => $ads->count(),
            'active_campaigns' => $ads->where('status', 'active')->count(),
            'total_spent' => $ads->sum('total_spent'),
            'total_impressions' => $ads->sum('impressions_count'),
            'total_clicks' => $ads->sum('clicks_count'),
            'total_conversions' => $ads->sum('conversions_count'),
            'average_ctr' => $ads->avg(fn ($ad) => $ad->getCTR()),
            'average_conversion_rate' => $ads->avg(fn ($ad) => $ad->getConversionRate()),
        ];
    }

    public function pauseAdvertisement(int $adId): bool
    {
        return Advertisement::where('id', $adId)
            ->update(['status' => 'paused']);
    }

    public function resumeAdvertisement(int $adId): bool
    {
        return Advertisement::where('id', $adId)
            ->update(['status' => 'active']);
    }
}
