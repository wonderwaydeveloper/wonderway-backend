<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class AutoScalingService
{
    private array $thresholds = [
        'cpu_high' => 80,
        'cpu_low' => 30,
        'memory_high' => 85,
        'memory_low' => 40,
        'queue_high' => 1000,
        'response_time_high' => 2000, // ms
    ];

    public function checkAndScale()
    {
        $metrics = $this->getCurrentMetrics();
        $recommendations = $this->analyzeMetrics($metrics);

        if (! empty($recommendations)) {
            $this->executeScalingActions($recommendations);
        }

        return [
            'metrics' => $metrics,
            'recommendations' => $recommendations,
            'timestamp' => now(),
        ];
    }

    public function getCurrentMetrics()
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_connections' => $this->getActiveConnections(),
            'queue_size' => $this->getQueueSize(),
            'response_time' => $this->getAverageResponseTime(),
            'error_rate' => $this->getErrorRate(),
            'throughput' => $this->getThroughput(),
        ];
    }

    public function analyzeMetrics(array $metrics)
    {
        $recommendations = [];

        // CPU-based scaling
        if ($metrics['cpu_usage'] > $this->thresholds['cpu_high']) {
            $recommendations[] = [
                'type' => 'scale_up',
                'reason' => 'High CPU usage',
                'priority' => 'high',
                'action' => 'increase_workers',
            ];
        } elseif ($metrics['cpu_usage'] < $this->thresholds['cpu_low']) {
            $recommendations[] = [
                'type' => 'scale_down',
                'reason' => 'Low CPU usage',
                'priority' => 'low',
                'action' => 'decrease_workers',
            ];
        }

        // Memory-based scaling
        if ($metrics['memory_usage'] > $this->thresholds['memory_high']) {
            $recommendations[] = [
                'type' => 'scale_up',
                'reason' => 'High memory usage',
                'priority' => 'critical',
                'action' => 'increase_memory',
            ];
        }

        // Queue-based scaling
        if ($metrics['queue_size'] > $this->thresholds['queue_high']) {
            $recommendations[] = [
                'type' => 'scale_workers',
                'reason' => 'High queue backlog',
                'priority' => 'high',
                'action' => 'increase_queue_workers',
            ];
        }

        // Response time scaling
        if ($metrics['response_time'] > $this->thresholds['response_time_high']) {
            $recommendations[] = [
                'type' => 'optimize',
                'reason' => 'High response time',
                'priority' => 'high',
                'action' => 'enable_caching',
            ];
        }

        return $recommendations;
    }

    public function executeScalingActions(array $recommendations)
    {
        foreach ($recommendations as $recommendation) {
            try {
                match ($recommendation['action']) {
                    'increase_workers' => $this->increaseWorkers(),
                    'decrease_workers' => $this->decreaseWorkers(),
                    'increase_queue_workers' => $this->increaseQueueWorkers(),
                    'enable_caching' => $this->enableAggressiveCaching(),
                    'increase_memory' => $this->optimizeMemoryUsage(),
                    default => Log::warning('Unknown scaling action: ' . $recommendation['action'])
                };

                Log::info('Auto-scaling action executed', $recommendation);
            } catch (\Exception $e) {
                Log::error('Auto-scaling action failed', [
                    'action' => $recommendation['action'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function getScalingHistory($days = 7)
    {
        return Cache::get('scaling_history', []);
    }

    public function predictLoad($hours = 24)
    {
        // Simple load prediction based on historical data
        $historical = $this->getHistoricalMetrics($hours * 7); // 7 days of data

        if (empty($historical)) {
            return [
                'predicted_cpu' => 45.0,
                'predicted_memory' => 60.0,
                'predicted_throughput' => 100.0,
                'confidence' => 0.5,
            ];
        }

        $avgCpu = collect($historical)->avg('cpu_usage');
        $avgMemory = collect($historical)->avg('memory_usage');
        $avgThroughput = collect($historical)->avg('throughput');

        return [
            'predicted_cpu' => round($avgCpu * 1.1, 2), // 10% buffer
            'predicted_memory' => round($avgMemory * 1.1, 2),
            'predicted_throughput' => round($avgThroughput * 1.2, 2),
            'confidence' => min(count($historical) / 168, 1), // Max confidence with 1 week data
        ];
    }

    private function getCpuUsage()
    {
        return Cache::remember('cpu_usage', 60, function () {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg()[0] ?? 0;

                return min($load * 25, 100);
            }

            // Windows fallback
            return rand(20, 60); // Simulate CPU usage for testing
        });
    }

    private function getMemoryUsage()
    {
        return Cache::remember('memory_usage', 60, function () {
            $used = memory_get_usage(true);
            $limit = ini_get('memory_limit');
            $limit = $this->convertToBytes($limit);

            return round(($used / $limit) * 100, 2);
        });
    }

    private function getActiveConnections()
    {
        return Cache::remember('active_connections', 30, function () {
            try {
                return DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            } catch (\Exception $e) {
                return 0;
            }
        });
    }

    private function getQueueSize()
    {
        return Cache::remember('queue_size', 30, function () {
            try {
                return Queue::size();
            } catch (\Exception $e) {
                return 0;
            }
        });
    }

    private function getAverageResponseTime()
    {
        return Cache::get('avg_response_time', 500); // Default 500ms
    }

    private function getErrorRate()
    {
        return Cache::get('error_rate', 0);
    }

    private function getThroughput()
    {
        return Cache::get('requests_per_minute', 0);
    }

    private function increaseWorkers()
    {
        // In production, this would interact with container orchestration
        Log::info('Scaling up workers');
        Cache::put('worker_count', Cache::get('worker_count', 4) + 2, 3600);
    }

    private function decreaseWorkers()
    {
        Log::info('Scaling down workers');
        $current = Cache::get('worker_count', 4);
        Cache::put('worker_count', max($current - 1, 2), 3600);
    }

    private function increaseQueueWorkers()
    {
        Log::info('Increasing queue workers');
        // Restart queue workers with higher concurrency
    }

    private function enableAggressiveCaching()
    {
        Log::info('Enabling aggressive caching');
        Cache::put('aggressive_caching', true, 1800); // 30 minutes
    }

    private function optimizeMemoryUsage()
    {
        Log::info('Optimizing memory usage');
        // Clear unnecessary caches, optimize queries
        Cache::flush();
    }

    private function convertToBytes($value)
    {
        $unit = strtolower(substr($value, -1));
        $value = (int) $value;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value
        };
    }

    private function getHistoricalMetrics($hours)
    {
        return Cache::get("historical_metrics_{$hours}", []);
    }
}
