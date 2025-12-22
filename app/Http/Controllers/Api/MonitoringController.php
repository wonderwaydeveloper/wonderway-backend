<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DatabaseService;
use App\Services\QueueManager;
use App\Services\RedisClusterService;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    // Remove constructor - middleware will be applied in routes

    public function dashboard()
    {
        return response()->json([
            'database' => $this->getDatabaseStats(),
            'cache' => $this->getCacheStats(),
            'queue' => $this->getQueueStats(),
            'system' => $this->getSystemStats(),
        ]);
    }

    public function database(DatabaseService $databaseService)
    {
        return response()->json([
            'replication_lag' => $databaseService->checkReplicationLag(),
            'connections' => $this->getDatabaseConnections(),
            'slow_queries' => $this->getSlowQueries(),
        ]);
    }

    public function cache(RedisClusterService $redisService)
    {
        return response()->json([
            'cluster_info' => $redisService->getClusterInfo(),
            'node_health' => $redisService->checkNodeHealth(),
            'cache_stats' => $this->getCacheStats(),
        ]);
    }

    public function queue(QueueManager $queueManager)
    {
        return response()->json([
            'stats' => $queueManager->getQueueStats(),
            'failed_jobs' => $this->getFailedJobs(),
        ]);
    }

    private function getDatabaseStats(): array
    {
        try {
            return [
                'total_posts' => DB::table('posts')->count(),
                'total_users' => DB::table('users')->count(),
                'total_likes' => DB::table('likes')->count(),
                'total_comments' => DB::table('comments')->count(),
                'posts_today' => DB::table('posts')
                    ->whereDate('created_at', today())
                    ->count(),
                'active_users_today' => DB::table('users')
                    ->whereDate('updated_at', today())
                    ->count(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getCacheStats(): array
    {
        try {
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $info = $redis->info();

            return [
                'memory_used' => $info['used_memory_human'] ?? 'unknown',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getQueueStats(): array
    {
        $queueManager = app(QueueManager::class);

        return $queueManager->getQueueStats();
    }

    private function getSystemStats(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'uptime' => $this->getUptime(),
        ];
    }

    private function getDatabaseConnections(): array
    {
        try {
            $connections = DB::select('SHOW PROCESSLIST');

            return [
                'total' => count($connections),
                'active' => collect($connections)->where('Command', '!=', 'Sleep')->count(),
                'sleeping' => collect($connections)->where('Command', 'Sleep')->count(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getSlowQueries(): array
    {
        try {
            // This would require slow query log to be enabled
            return [
                'enabled' => false,
                'message' => 'Slow query log not configured',
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getFailedJobs(): array
    {
        try {
            return [
                'total' => DB::table('failed_jobs')->count(),
                'recent' => DB::table('failed_jobs')
                    ->orderBy('failed_at', 'desc')
                    ->limit(5)
                    ->get(['id', 'queue', 'exception', 'failed_at'])
                    ->toArray(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    private function getUptime(): string
    {
        $uptime = time() - filectime(__FILE__);

        return gmdate('H:i:s', $uptime);
    }
}
