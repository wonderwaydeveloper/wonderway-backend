<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class PerformanceMonitoringService
{
    private array $metrics = [];
    
    public function recordMetric(string $name, float $value, array $tags = []): void
    {
        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $this->metrics[] = $metric;
        
        // Store in Redis for real-time access
        Redis::lpush('performance_metrics', json_encode($metric));
        Redis::expire('performance_metrics', 3600); // 1 hour
        
        // Log critical metrics
        if ($this->isCriticalMetric($name, $value)) {
            Log::warning("Critical performance metric: {$name}", $metric);
        }
    }
    
    public function recordApiResponse(string $endpoint, float $responseTime, int $statusCode): void
    {
        $this->recordMetric('api_response_time', $responseTime, [
            'endpoint' => $endpoint,
            'status_code' => $statusCode
        ]);
        
        // Update endpoint statistics
        $key = "api_stats:{$endpoint}";
        Redis::hincrby($key, 'total_requests', 1);
        Redis::hincrbyfloat($key, 'total_response_time', $responseTime);
        
        if ($statusCode >= 400) {
            Redis::hincrby($key, 'error_count', 1);
        }
        
        Redis::expire($key, 86400); // 24 hours
    }
    
    public function recordDatabaseQuery(string $query, float $executionTime): void
    {
        $this->recordMetric('database_query_time', $executionTime, [
            'query_type' => $this->getQueryType($query)
        ]);
        
        // Track slow queries
        if ($executionTime > 1.0) { // Slower than 1 second
            Log::warning('Slow database query detected', [
                'query' => $query,
                'execution_time' => $executionTime
            ]);
        }
    }
    
    public function recordMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $this->recordMetric('memory_usage', $memoryUsage);
        $this->recordMetric('peak_memory_usage', $peakMemory);
        
        // Alert if memory usage is high
        if ($memoryUsage > 128 * 1024 * 1024) { // 128MB
            Log::warning('High memory usage detected', [
                'current' => $this->formatBytes($memoryUsage),
                'peak' => $this->formatBytes($peakMemory)
            ]);
        }
    }
    
    public function getMetrics(int $minutes = 60): array
    {
        $since = microtime(true) - ($minutes * 60);
        $metrics = Redis::lrange('performance_metrics', 0, -1);
        
        $filtered = [];
        foreach ($metrics as $metric) {
            $data = json_decode($metric, true);
            if ($data['timestamp'] >= $since) {
                $filtered[] = $data;
            }
        }
        
        return $filtered;
    }
    
    public function getApiStatistics(): array
    {
        $keys = Redis::keys('api_stats:*');
        $stats = [];
        
        foreach ($keys as $key) {
            $endpoint = str_replace('api_stats:', '', $key);
            $data = Redis::hgetall($key);
            
            $totalRequests = (int) ($data['total_requests'] ?? 0);
            $totalResponseTime = (float) ($data['total_response_time'] ?? 0);
            $errorCount = (int) ($data['error_count'] ?? 0);
            
            $stats[$endpoint] = [
                'total_requests' => $totalRequests,
                'average_response_time' => $totalRequests > 0 ? $totalResponseTime / $totalRequests : 0,
                'error_rate' => $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0,
                'error_count' => $errorCount
            ];
        }
        
        return $stats;
    }
    
    public function getDashboardData(): array
    {
        return [
            'system' => $this->getSystemMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'api' => $this->getApiStatistics(),
            'real_time' => $this->getRealTimeMetrics()
        ];
    }
    
    private function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'cpu_usage' => sys_getloadavg()[0] ?? 0,
            'disk_usage' => disk_free_space('/') ? (disk_total_space('/') - disk_free_space('/')) : 0,
            'uptime' => $this->getUptime()
        ];
    }
    
    private function getDatabaseMetrics(): array
    {
        try {
            $connections = DB::getConnections();
            $metrics = [];
            
            foreach ($connections as $name => $connection) {
                $metrics[$name] = [
                    'active_connections' => $this->getActiveConnections($connection),
                    'slow_queries' => $this->getSlowQueriesCount(),
                    'query_cache_hit_rate' => $this->getQueryCacheHitRate()
                ];
            }
            
            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get database metrics', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    private function getCacheMetrics(): array
    {
        try {
            $info = Redis::info();
            
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get cache metrics', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    private function getRealTimeMetrics(): array
    {
        $recentMetrics = $this->getMetrics(5); // Last 5 minutes
        
        $grouped = [];
        foreach ($recentMetrics as $metric) {
            $name = $metric['name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }
            $grouped[$name][] = $metric['value'];
        }
        
        $summary = [];
        foreach ($grouped as $name => $values) {
            $summary[$name] = [
                'count' => count($values),
                'average' => array_sum($values) / count($values),
                'min' => min($values),
                'max' => max($values)
            ];
        }
        
        return $summary;
    }
    
    private function isCriticalMetric(string $name, float $value): bool
    {
        $thresholds = [
            'api_response_time' => 2.0, // 2 seconds
            'database_query_time' => 1.0, // 1 second
            'memory_usage' => 256 * 1024 * 1024, // 256MB
        ];
        
        return isset($thresholds[$name]) && $value > $thresholds[$name];
    }
    
    private function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        
        if (str_starts_with($query, 'SELECT')) return 'SELECT';
        if (str_starts_with($query, 'INSERT')) return 'INSERT';
        if (str_starts_with($query, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($query, 'DELETE')) return 'DELETE';
        
        return 'OTHER';
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function getUptime(): int
    {
        if (function_exists('sys_getloadavg')) {
            $uptime = file_get_contents('/proc/uptime');
            return (int) floatval($uptime);
        }
        
        return 0;
    }
    
    private function getActiveConnections($connection): int
    {
        try {
            $result = $connection->select('SHOW STATUS LIKE "Threads_connected"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getSlowQueriesCount(): int
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Slow_queries"');
            return (int) ($result[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getQueryCacheHitRate(): float
    {
        try {
            $hits = DB::select('SHOW STATUS LIKE "Qcache_hits"');
            $inserts = DB::select('SHOW STATUS LIKE "Qcache_inserts"');
            
            $hitCount = (int) ($hits[0]->Value ?? 0);
            $insertCount = (int) ($inserts[0]->Value ?? 0);
            
            $total = $hitCount + $insertCount;
            return $total > 0 ? ($hitCount / $total) * 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function calculateHitRate(array $info): float
    {
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
}