<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PerformanceMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceDashboardController extends Controller
{
    public function __construct(
        private PerformanceMonitoringService $performanceService
    ) {
    }

    public function dashboard(): JsonResponse
    {
        $data = $this->performanceService->getDashboardData();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Performance dashboard data retrieved successfully',
        ]);
    }

    public function metrics(Request $request): JsonResponse
    {
        $minutes = $request->get('minutes', 60);
        $metrics = $this->performanceService->getMetrics($minutes);

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Performance metrics retrieved successfully',
        ]);
    }

    public function apiStats(): JsonResponse
    {
        $stats = $this->performanceService->getApiStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'API statistics retrieved successfully',
        ]);
    }

    public function realTimeMetrics(): JsonResponse
    {
        $metrics = $this->performanceService->getRealTimeMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Real-time metrics retrieved successfully',
        ]);
    }

    public function systemHealth(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'memory' => $this->checkMemory(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        // Determine overall status
        $failedChecks = array_filter($health['checks'], fn ($check) => ! $check['healthy']);
        if (! empty($failedChecks)) {
            $health['status'] = count($failedChecks) > 1 ? 'critical' : 'warning';
        }

        return response()->json([
            'success' => true,
            'data' => $health,
            'message' => 'System health check completed',
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            $responseTime = $this->measureExecutionTime(function () {
                \DB::select('SELECT 1');
            });

            return [
                'healthy' => true,
                'response_time' => $responseTime,
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'response_time' => null,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . time();
            $value = 'test_value';

            $responseTime = $this->measureExecutionTime(function () use ($key, $value) {
                \Cache::put($key, $value, 60);
                $retrieved = \Cache::get($key);
                \Cache::forget($key);

                if ($retrieved !== $value) {
                    throw new \Exception('Cache value mismatch');
                }
            });

            return [
                'healthy' => true,
                'response_time' => $responseTime,
                'message' => 'Cache system operational',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'response_time' => null,
                'message' => 'Cache system failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $diskFree = disk_free_space(storage_path());
            $diskTotal = disk_total_space(storage_path());
            $usagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

            $healthy = $usagePercent < 90; // Alert if disk usage > 90%

            return [
                'healthy' => $healthy,
                'usage_percent' => round($usagePercent, 2),
                'free_space' => $this->formatBytes($diskFree),
                'total_space' => $this->formatBytes($diskTotal),
                'message' => $healthy ? 'Storage space sufficient' : 'Low disk space warning',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => 'Storage check failed: ' . $e->getMessage(),
            ];
        }
    }

    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        $usagePercent = ($memoryUsage / $memoryLimit) * 100;

        $healthy = $usagePercent < 80; // Alert if memory usage > 80%

        return [
            'healthy' => $healthy,
            'usage_percent' => round($usagePercent, 2),
            'current_usage' => $this->formatBytes($memoryUsage),
            'memory_limit' => $this->formatBytes($memoryLimit),
            'peak_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            'message' => $healthy ? 'Memory usage normal' : 'High memory usage detected',
        ];
    }

    private function measureExecutionTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);

        return round(($end - $start) * 1000, 2); // Convert to milliseconds
    }

    private function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
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
}
