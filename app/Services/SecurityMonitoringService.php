<?php

namespace App\Services;

use App\Notifications\SecurityAlert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;

class SecurityMonitoringService
{
    private array $alertThresholds = [
        'failed_logins' => 10,
        'blocked_requests' => 50,
        'suspicious_activities' => 5,
        'data_breaches' => 1,
        'privilege_escalations' => 1,
    ];

    private array $monitoredEvents = [
        'authentication.failed',
        'request.blocked',
        'data.unauthorized_access',
        'user.privilege_change',
        'security.threat_detected',
        'system.anomaly_detected',
    ];

    public function startMonitoring(): void
    {
        Log::info('Security monitoring service started');

        // Start real-time event processing
        $this->processSecurityEvents();

        // Start anomaly detection
        $this->detectAnomalies();

        // Start threat intelligence updates
        $this->updateThreatIntelligence();
    }

    public function logSecurityEvent(string $event, array $data = []): void
    {
        if (! in_array($event, $this->monitoredEvents)) {
            return;
        }

        $eventData = [
            'event' => $event,
            'timestamp' => now()->toISOString(),
            'data' => $data,
            'severity' => $this->calculateSeverity($event, $data),
            'source_ip' => request()?->ip(),
            'user_id' => auth()?->id(),
        ];

        // Store in Redis for real-time processing
        Redis::lpush('security_events', json_encode($eventData));

        // Log to security channel
        Log::channel('security')->info("Security event: {$event}", $eventData);

        // Check for immediate alerts
        $this->checkAlertConditions($event, $eventData);
    }

    private function processSecurityEvents(): void
    {
        while (true) {
            $event = Redis::brpop(['security_events'], 1);

            if ($event) {
                $eventData = json_decode($event[1], true);
                $this->analyzeEvent($eventData);
            }

            // Prevent infinite loop in testing
            if (app()->environment('testing')) {
                break;
            }
        }
    }

    private function analyzeEvent(array $eventData): void
    {
        $event = $eventData['event'];
        $severity = $eventData['severity'];

        // Update metrics
        $this->updateSecurityMetrics($event, $severity);

        // Pattern detection
        $this->detectPatterns($eventData);

        // Correlation analysis
        $this->correlateEvents($eventData);

        // Auto-response
        if ($severity === 'critical') {
            $this->triggerAutoResponse($eventData);
        }
    }

    private function detectAnomalies(): void
    {
        // Detect unusual patterns in user behavior
        $this->detectUserAnomalies();

        // Detect system anomalies
        $this->detectSystemAnomalies();

        // Detect network anomalies
        $this->detectNetworkAnomalies();
    }

    private function detectUserAnomalies(): void
    {
        $users = Cache::remember('active_users', 300, function () {
            return \App\Models\User::where('last_activity', '>=', now()->subHour())->get();
        });

        foreach ($users as $user) {
            $baseline = $this->getUserBaseline($user->id);
            $current = $this->getCurrentUserActivity($user->id);

            if ($this->isAnomalousActivity($baseline, $current)) {
                $this->logSecurityEvent('user.anomaly_detected', [
                    'user_id' => $user->id,
                    'baseline' => $baseline,
                    'current' => $current,
                    'anomaly_score' => $this->calculateAnomalyScore($baseline, $current),
                ]);
            }
        }
    }

    private function detectSystemAnomalies(): void
    {
        $metrics = [
            'cpu_usage' => sys_getloadavg()[0],
            'memory_usage' => memory_get_usage(true),
            'disk_usage' => disk_free_space('/'),
            'active_connections' => $this->getActiveConnections(),
        ];

        foreach ($metrics as $metric => $value) {
            $threshold = $this->getSystemThreshold($metric);

            if ($value > $threshold) {
                $this->logSecurityEvent('system.anomaly_detected', [
                    'metric' => $metric,
                    'value' => $value,
                    'threshold' => $threshold,
                ]);
            }
        }
    }

    private function detectNetworkAnomalies(): void
    {
        $networkStats = [
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'unique_ips' => $this->getUniqueIPs(),
            'error_rate' => $this->getErrorRate(),
            'response_time' => $this->getAverageResponseTime(),
        ];

        foreach ($networkStats as $stat => $value) {
            $baseline = $this->getNetworkBaseline($stat);

            if ($this->isNetworkAnomaly($stat, $value, $baseline)) {
                $this->logSecurityEvent('network.anomaly_detected', [
                    'stat' => $stat,
                    'value' => $value,
                    'baseline' => $baseline,
                ]);
            }
        }
    }

    private function checkAlertConditions(string $event, array $eventData): void
    {
        $eventType = explode('.', $event)[0];
        $count = $this->getEventCount($eventType, 3600); // Last hour

        if (isset($this->alertThresholds[$eventType]) &&
            $count >= $this->alertThresholds[$eventType]) {

            $this->sendSecurityAlert($eventType, $count, $eventData);
        }
    }

    private function sendSecurityAlert(string $eventType, int $count, array $eventData): void
    {
        $alert = [
            'type' => 'security_threshold_exceeded',
            'event_type' => $eventType,
            'count' => $count,
            'threshold' => $this->alertThresholds[$eventType],
            'last_event' => $eventData,
            'timestamp' => now(),
        ];

        // Send to security team
        Notification::route('mail', config('security.alert_email'))
            ->notify(new SecurityAlert($alert));

        // Send to Slack/Discord if configured
        if (config('security.slack_webhook')) {
            $this->sendSlackAlert($alert);
        }

        Log::critical('Security alert triggered', $alert);
    }

    private function updateSecurityMetrics(string $event, string $severity): void
    {
        $key = "security_metrics:" . date('Y-m-d-H');

        Redis::hincrby($key, "events_total", 1);
        Redis::hincrby($key, "events_{$severity}", 1);
        Redis::hincrby($key, str_replace('.', '_', $event), 1);
        Redis::expire($key, 86400 * 7); // Keep for 7 days
    }

    private function calculateSeverity(string $event, array $data): string
    {
        $criticalEvents = [
            'data.unauthorized_access',
            'user.privilege_change',
            'security.breach_detected',
        ];

        $highEvents = [
            'authentication.failed',
            'security.threat_detected',
        ];

        if (in_array($event, $criticalEvents)) {
            return 'critical';
        }

        if (in_array($event, $highEvents)) {
            return 'high';
        }

        return 'medium';
    }

    private function triggerAutoResponse(array $eventData): void
    {
        $event = $eventData['event'];
        $sourceIp = $eventData['source_ip'] ?? null;

        switch ($event) {
            case 'security.threat_detected':
                if ($sourceIp) {
                    $this->blockIP($sourceIp, 3600); // Block for 1 hour
                }

                break;

            case 'data.unauthorized_access':
                $this->enableEmergencyMode();

                break;

            case 'user.privilege_change':
                $this->auditUserPermissions($eventData['data']['user_id'] ?? null);

                break;
        }
    }

    private function blockIP(string $ip, int $duration): void
    {
        Cache::put("blocked_ip:{$ip}", true, now()->addSeconds($duration));
        Log::warning("IP blocked automatically: {$ip}");
    }

    private function enableEmergencyMode(): void
    {
        Cache::put('emergency_mode', true, now()->addHour());
        Log::critical('Emergency mode enabled');
    }

    // Helper methods
    private function getUserBaseline(int $userId): array
    {
        return Cache::remember("user_baseline:{$userId}", 3600, function () use ($userId) {
            // Calculate user's normal behavior patterns
            return [
                'avg_requests_per_hour' => 50,
                'common_ips' => ['192.168.1.1'],
                'typical_hours' => [9, 10, 11, 14, 15, 16],
                'common_endpoints' => ['/api/moments', '/api/user'],
            ];
        });
    }

    private function getCurrentUserActivity(int $userId): array
    {
        // Get current user activity metrics
        return [
            'requests_last_hour' => 75,
            'current_ip' => request()?->ip(),
            'current_hour' => now()->hour,
            'recent_endpoints' => ['/api/admin', '/api/users'],
        ];
    }

    private function isAnomalousActivity(array $baseline, array $current): bool
    {
        // Simple anomaly detection logic
        return $current['requests_last_hour'] > $baseline['avg_requests_per_hour'] * 2;
    }

    private function calculateAnomalyScore(array $baseline, array $current): float
    {
        return ($current['requests_last_hour'] / $baseline['avg_requests_per_hour']) * 100;
    }

    private function getEventCount(string $eventType, int $timeframe): int
    {
        $key = "event_count:{$eventType}";

        return (int) Cache::get($key, 0);
    }

    private function getSystemThreshold(string $metric): float
    {
        $thresholds = [
            'cpu_usage' => 80.0,
            'memory_usage' => 1024 * 1024 * 1024, // 1GB
            'disk_usage' => 1024 * 1024 * 1024 * 10, // 10GB
            'active_connections' => 1000,
        ];

        return $thresholds[$metric] ?? 100.0;
    }

    private function getActiveConnections(): int
    {
        // Implementation would depend on your server setup
        return 50; // Placeholder
    }

    private function getRequestsPerMinute(): int
    {
        return (int) Cache::get('requests_per_minute', 0);
    }

    private function getUniqueIPs(): int
    {
        return (int) Cache::get('unique_ips_count', 0);
    }

    private function getErrorRate(): float
    {
        return (float) Cache::get('error_rate', 0.0);
    }

    private function getAverageResponseTime(): float
    {
        return (float) Cache::get('avg_response_time', 0.0);
    }

    private function getNetworkBaseline(string $stat): array
    {
        return Cache::remember("network_baseline:{$stat}", 3600, function () {
            return ['avg' => 100, 'max' => 200, 'min' => 10];
        });
    }

    private function isNetworkAnomaly(string $stat, $value, array $baseline): bool
    {
        return $value > $baseline['max'] * 1.5;
    }

    private function sendSlackAlert(array $alert): void
    {
        // Implementation for Slack notifications
        Log::info('Slack alert would be sent', $alert);
    }

    private function detectPatterns(array $eventData): void
    {
        // Pattern detection implementation
    }

    private function correlateEvents(array $eventData): void
    {
        // Event correlation implementation
    }

    private function updateThreatIntelligence(): void
    {
        // Threat intelligence updates
    }

    private function auditUserPermissions(?int $userId): void
    {
        if ($userId) {
            Log::info("Auditing permissions for user: {$userId}");
        }
    }
}
