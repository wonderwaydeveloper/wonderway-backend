<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class AdvancedApiRateLimit
{
    private array $endpointLimits = [
        'api/login' => ['attempts' => 5, 'decay' => 900], // 5 attempts per 15 min
        'api/register' => ['attempts' => 3, 'decay' => 3600], // 3 attempts per hour
        'api/moments' => ['attempts' => 100, 'decay' => 3600], // 100 per hour
        'api/follow' => ['attempts' => 50, 'decay' => 3600], // 50 per hour
        'api/upload' => ['attempts' => 20, 'decay' => 3600], // 20 per hour
    ];

    public function handle(Request $request, \Closure $next): Response
    {
        $endpoint = $request->route()->uri();
        $userId = $request->user()?->id ?? $request->ip();

        $limits = $this->endpointLimits[$endpoint] ?? ['attempts' => 60, 'decay' => 60];

        $key = "api_limit:{$endpoint}:{$userId}";

        if (RateLimiter::tooManyAttempts($key, $limits['attempts'])) {
            $this->logSuspiciousActivity($request, $userId, $endpoint);

            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        RateLimiter::hit($key, $limits['decay']);

        return $next($request);
    }

    private function logSuspiciousActivity(Request $request, $userId, string $endpoint): void
    {
        Redis::lpush('suspicious_activity', json_encode([
            'user_id' => $userId,
            'ip' => $request->ip(),
            'endpoint' => $endpoint,
            'user_agent' => $request->userAgent(),
            'timestamp' => now(),
            'type' => 'rate_limit_exceeded',
        ]));
    }
}
