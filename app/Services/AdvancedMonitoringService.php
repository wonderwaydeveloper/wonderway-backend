<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AdvancedMonitoringService
{
    public function getSystemMetrics(): array
    {
        return [
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'memory' => $this->getMemoryMetrics(),
        ];
    }

    public function getDatabaseMetrics(): array
    {
        $queries = DB::getQueryLog();
        $slowQueries = collect($queries)->where('time', '>', 100);
        
        return [
            'total_queries' => count($queries),
            'slow_queries' => $slowQueries->count(),
            'avg_query_time' => collect($queries)->avg('time'),
            'connections' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_CONNECTION_STATUS),
        ];
    }

    public function getCacheMetrics(): array
    {
        $redis = Redis::connection();
        $info = $redis->info();
        
        return [
            'hit_rate' => Cache::get('cache_hit_rate', 0),
            'memory_usage' => $info['used_memory_human'] ?? 'N/A',
            'connected_clients' => $info['connected_clients'] ?? 0,
            'total_commands' => $info['total_commands_processed'] ?? 0,
        ];
    }

    public function getQueueMetrics(): array
    {
        return [
            'pending_jobs' => Redis::llen('queues:default'),
            'failed_jobs' => Redis::llen('queues:failed'),
            'processed_jobs' => Cache::get('processed_jobs_count', 0),
        ];
    }

    public function getMemoryMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    public function recordMetric(string $key, mixed $value): void
    {
        Cache::put("metrics:{$key}:" . now()->format('Y-m-d-H'), $value, 3600);
    }

    public function getHistoricalMetrics(string $key, int $hours = 24): array
    {
        $metrics = [];
        for ($i = 0; $i < $hours; $i++) {
            $timestamp = now()->subHours($i)->format('Y-m-d-H');
            $metrics[$timestamp] = Cache::get("metrics:{$key}:{$timestamp}", 0);
        }
        return array_reverse($metrics);
    }
}