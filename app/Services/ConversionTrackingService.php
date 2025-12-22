<?php

namespace App\Services;

use App\Models\ConversionMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConversionTrackingService
{
    public function track($eventType, $userId = null, $eventData = [], $conversionValue = 0)
    {
        $conversionType = $this->determineConversionType($eventType);

        ConversionMetric::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_data' => $eventData,
            'conversion_type' => $conversionType,
            'conversion_value' => $conversionValue,
            'source' => request()->header('referer') ? 'referral' : 'direct',
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Update real-time metrics cache
        $this->updateRealTimeMetrics($conversionType);
    }

    public function getConversionFunnel($dateRange = 7)
    {
        $startDate = Carbon::now()->subDays($dateRange);

        return Cache::remember("conversion_funnel_{$dateRange}", 3600, function () use ($startDate) {
            return [
                'visitors' => $this->getUniqueVisitors($startDate),
                'signups' => $this->getConversions('registration', $startDate),
                'active_users' => $this->getActiveUsers($startDate),
                'premium_subscriptions' => $this->getConversions('monetization', $startDate),
                'conversion_rates' => $this->calculateConversionRates($startDate),
            ];
        });
    }

    public function getConversionsBySource($dateRange = 30)
    {
        $startDate = Carbon::now()->subDays($dateRange);

        return ConversionMetric::select('source', DB::raw('COUNT(*) as conversions'), DB::raw('SUM(conversion_value) as total_value'))
            ->where('created_at', '>=', $startDate)
            ->groupBy('source')
            ->orderBy('conversions', 'desc')
            ->get();
    }

    public function getUserJourney($userId)
    {
        return ConversionMetric::where('user_id', $userId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($metric) {
                return [
                    'event' => $metric->event_type,
                    'timestamp' => $metric->created_at,
                    'data' => $metric->event_data,
                    'value' => $metric->conversion_value,
                ];
            });
    }

    public function getCohortAnalysis($period = 'weekly')
    {
        $cacheKey = "cohort_analysis_{$period}";

        return Cache::remember($cacheKey, 7200, function () use ($period) {
            // Simplified cohort analysis
            $cohorts = [];
            $startDate = Carbon::now()->subMonths(6);

            while ($startDate->lt(Carbon::now())) {
                $endDate = $period === 'weekly' ? $startDate->copy()->addWeek() : $startDate->copy()->addMonth();

                $newUsers = ConversionMetric::where('event_type', 'signup')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->count();

                $retainedUsers = ConversionMetric::where('event_type', 'login')
                    ->whereBetween('created_at', [$endDate, $endDate->copy()->addWeek()])
                    ->whereIn('user_id', function ($query) use ($startDate, $endDate) {
                        $query->select('user_id')
                            ->from('conversion_metrics')
                            ->where('event_type', 'signup')
                            ->whereBetween('created_at', [$startDate, $endDate]);
                    })
                    ->distinct('user_id')
                    ->count();

                $cohorts[] = [
                    'period' => $startDate->format('Y-m-d'),
                    'new_users' => $newUsers,
                    'retained_users' => $retainedUsers,
                    'retention_rate' => $newUsers > 0 ? round(($retainedUsers / $newUsers) * 100, 2) : 0,
                ];

                $startDate = $endDate;
            }

            return $cohorts;
        });
    }

    private function determineConversionType($eventType)
    {
        return match ($eventType) {
            'signup', 'email_verify', 'profile_complete' => 'registration',
            'post_create', 'comment', 'like', 'follow' => 'engagement',
            'subscription', 'premium_upgrade', 'payment' => 'monetization',
            default => 'other'
        };
    }

    private function getUniqueVisitors($startDate)
    {
        return ConversionMetric::where('created_at', '>=', $startDate)
            ->distinct('ip_address')
            ->count();
    }

    private function getConversions($type, $startDate)
    {
        return ConversionMetric::where('conversion_type', $type)
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    private function getActiveUsers($startDate)
    {
        return ConversionMetric::whereIn('event_type', ['login', 'post_create', 'comment', 'like'])
            ->where('created_at', '>=', $startDate)
            ->distinct('user_id')
            ->count();
    }

    private function calculateConversionRates($startDate)
    {
        $visitors = $this->getUniqueVisitors($startDate);
        $signups = $this->getConversions('registration', $startDate);
        $active = $this->getActiveUsers($startDate);
        $premium = $this->getConversions('monetization', $startDate);

        return [
            'visitor_to_signup' => $visitors > 0 ? round(($signups / $visitors) * 100, 2) : 0,
            'signup_to_active' => $signups > 0 ? round(($active / $signups) * 100, 2) : 0,
            'active_to_premium' => $active > 0 ? round(($premium / $active) * 100, 2) : 0,
        ];
    }

    private function updateRealTimeMetrics($conversionType)
    {
        $key = "realtime_conversions_{$conversionType}";
        Cache::put($key, Cache::get($key, 0) + 1, 3600);
    }
}
