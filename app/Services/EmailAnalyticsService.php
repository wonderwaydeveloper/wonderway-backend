<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EmailAnalyticsService
{
    public function trackEmailSent(string $emailId, User $user, string $type): void
    {
        DB::table('email_analytics')->insert([
            'email_id' => $emailId,
            'user_id' => $user->id,
            'email_type' => $type,
            'status' => 'sent',
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function trackEmailOpened(string $emailId): void
    {
        DB::table('email_analytics')
            ->where('email_id', $emailId)
            ->update([
                'status' => 'opened',
                'opened_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function trackEmailClicked(string $emailId, string $link): void
    {
        DB::table('email_analytics')
            ->where('email_id', $emailId)
            ->update([
                'status' => 'clicked',
                'clicked_at' => now(),
                'clicked_link' => $link,
                'updated_at' => now(),
            ]);
    }

    public function getEmailMetrics(int $days = 30): array
    {
        $cacheKey = "email_metrics_{$days}_days";

        return Cache::remember($cacheKey, 3600, function () use ($days) {
            $startDate = now()->subDays($days);

            $metrics = DB::table('email_analytics')
                ->where('sent_at', '>=', $startDate)
                ->selectRaw('
                    COUNT(*) as total_sent,
                    COUNT(opened_at) as total_opened,
                    COUNT(clicked_at) as total_clicked,
                    ROUND(COUNT(opened_at) * 100.0 / COUNT(*), 2) as open_rate,
                    ROUND(COUNT(clicked_at) * 100.0 / COUNT(*), 2) as click_rate,
                    ROUND(COUNT(clicked_at) * 100.0 / COUNT(opened_at), 2) as click_to_open_rate
                ')
                ->first();

            $byType = DB::table('email_analytics')
                ->where('sent_at', '>=', $startDate)
                ->groupBy('email_type')
                ->selectRaw('
                    email_type,
                    COUNT(*) as sent,
                    COUNT(opened_at) as opened,
                    COUNT(clicked_at) as clicked,
                    ROUND(COUNT(opened_at) * 100.0 / COUNT(*), 2) as open_rate
                ')
                ->get();

            return [
                'overview' => $metrics,
                'by_type' => $byType,
                'period' => $days . ' days',
            ];
        });
    }

    public function getUserEmailStats(int $userId): array
    {
        return DB::table('email_analytics')
            ->where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_received,
                COUNT(opened_at) as total_opened,
                COUNT(clicked_at) as total_clicked,
                MAX(opened_at) as last_opened,
                email_type
            ')
            ->groupBy('email_type')
            ->get()
            ->toArray();
    }

    public function getTopPerformingEmails(int $limit = 10): array
    {
        return DB::table('email_analytics')
            ->selectRaw('
                email_type,
                COUNT(*) as sent,
                COUNT(opened_at) as opened,
                COUNT(clicked_at) as clicked,
                ROUND(COUNT(opened_at) * 100.0 / COUNT(*), 2) as open_rate,
                ROUND(COUNT(clicked_at) * 100.0 / COUNT(*), 2) as click_rate
            ')
            ->groupBy('email_type')
            ->having('sent', '>=', 10)
            ->orderByDesc('open_rate')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function generatePixelTrackingUrl(string $emailId): string
    {
        return url("/api/email/track/open/{$emailId}");
    }

    public function generateClickTrackingUrl(string $emailId, string $originalUrl): string
    {
        $encodedUrl = base64_encode($originalUrl);

        return url("/api/email/track/click/{$emailId}?url={$encodedUrl}");
    }
}
