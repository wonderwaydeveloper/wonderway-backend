<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CacheManagementService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    public function __construct(
        private CacheManagementService $cacheService,
        private DatabaseOptimizationService $dbService
    ) {
    }

    public function dashboard()
    {
        $stats = [
            'cache' => $this->cacheService->getCacheStats(),
            'database' => $this->getDatabaseStats(),
            'performance' => $this->getPerformanceMetrics(),
        ];

        return response()->json($stats);
    }

    public function optimizeTimeline(Request $request)
    {
        $user = $request->user();
        $posts = $this->dbService->optimizeTimeline($user->id);

        return response()->json([
            'posts' => $posts,
            'cached' => true,
            'performance' => 'optimized',
        ]);
    }

    public function warmupCache()
    {
        $this->cacheService->warmupCache();

        return response()->json([
            'message' => 'Cache warmed up successfully',
            'timestamp' => now(),
        ]);
    }

    public function clearCache(Request $request)
    {
        $type = $request->input('type', 'all');

        switch ($type) {
            case 'user':
                $userId = $request->input('user_id');
                $this->cacheService->invalidateUserCache($userId);

                break;
            case 'posts':
                Cache::forget('posts:popular:24h');
                Cache::forget('posts:public:*');

                break;
            case 'all':
                Cache::flush();

                break;
        }

        return response()->json([
            'message' => "Cache cleared: {$type}",
            'timestamp' => now(),
        ]);
    }

    private function getDatabaseStats()
    {
        $stats = DB::select("
            SELECT 
                table_name,
                table_rows,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY size_mb DESC
            LIMIT 10
        ");

        return [
            'tables' => $stats,
            'connections' => DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0,
        ];
    }

    private function getPerformanceMetrics()
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $startTime,
            'queries_count' => 0, // Will be populated if query log is enabled
        ];
    }
}
