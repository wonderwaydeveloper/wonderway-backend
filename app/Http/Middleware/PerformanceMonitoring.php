<?php

namespace App\Http\Middleware;

use App\Services\PerformanceMonitoringService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    public function __construct(
        private PerformanceMonitoringService $performanceService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Process request
        $response = $next($request);
        
        // Calculate metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;
        
        // Record metrics
        $endpoint = $request->getPathInfo();
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        
        $this->performanceService->recordApiResponse(
            "{$method} {$endpoint}",
            $responseTime,
            $statusCode
        );
        
        $this->performanceService->recordMetric('request_memory_usage', $memoryUsed, [
            'endpoint' => $endpoint,
            'method' => $method
        ]);
        
        // Add performance headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-Response-Time', round($responseTime, 2) . 'ms');
            $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsed));
            $response->headers->set('X-Peak-Memory', $this->formatBytes(memory_get_peak_usage(true)));
        }
        
        return $response;
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