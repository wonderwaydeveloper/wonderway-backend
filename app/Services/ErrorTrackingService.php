<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ErrorTrackingService
{
    public function trackError(Throwable $exception, array $context = []): void
    {
        $errorData = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context,
            'timestamp' => now(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Log to file
        Log::error('Application Error', $errorData);

        // Store in cache for dashboard
        $this->storeErrorMetrics($exception);
    }

    private function storeErrorMetrics(Throwable $exception): void
    {
        $key = 'error_metrics:' . now()->format('Y-m-d-H');
        $errors = Cache::get($key, []);
        
        $errorType = get_class($exception);
        $errors[$errorType] = ($errors[$errorType] ?? 0) + 1;
        
        Cache::put($key, $errors, 3600);
    }

    public function getErrorStats(int $hours = 24): array
    {
        $stats = [];
        for ($i = 0; $i < $hours; $i++) {
            $key = 'error_metrics:' . now()->subHours($i)->format('Y-m-d-H');
            $hourlyErrors = Cache::get($key, []);
            
            foreach ($hourlyErrors as $type => $count) {
                $stats[$type] = ($stats[$type] ?? 0) + $count;
            }
        }
        
        return $stats;
    }

    public function getTopErrors(int $limit = 10): array
    {
        $stats = $this->getErrorStats();
        arsort($stats);
        
        return array_slice($stats, 0, $limit, true);
    }
}