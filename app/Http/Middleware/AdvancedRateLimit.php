<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class AdvancedRateLimit
{
    public function handle(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $identifier = $this->resolveRequestSignature($request, $key);

        // Check if user is suspicious (from spam middleware)
        if ($request->has('_spam_suspicious')) {
            $maxAttempts = (int)($maxAttempts * 0.3); // Reduce limit by 70%
        }

        // Enhanced rate limiting with Redis
        if ($this->tooManyAttempts($identifier, $maxAttempts, $decayMinutes)) {
            return $this->buildResponse($identifier, $maxAttempts, $decayMinutes);
        }

        $this->hit($identifier, $decayMinutes);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($identifier, $maxAttempts)
        );
    }

    protected function resolveRequestSignature(Request $request, string $key): string
    {
        $user = $request->user();

        if ($user) {
            return sha1($key . '|' . $user->id);
        }

        // For guests, use IP + User Agent for better tracking
        return sha1($key . '|' . $request->ip() . '|' . $request->userAgent());
    }

    protected function tooManyAttempts(string $key, int $maxAttempts, int $decayMinutes): bool
    {
        $attempts = Redis::get($key) ?? 0;

        return $attempts >= $maxAttempts;
    }

    protected function hit(string $key, int $decayMinutes): int
    {
        $current = Redis::incr($key);

        if ($current === 1) {
            Redis::expire($key, $decayMinutes * 60);
        }

        return $current;
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        $attempts = Redis::get($key) ?? 0;

        return max(0, $maxAttempts - $attempts);
    }

    protected function buildResponse(string $key, int $maxAttempts, int $decayMinutes)
    {
        $retryAfter = Redis::ttl($key);

        return response()->json([
            'message' => 'تعداد درخواست‌های شما از حد مجاز گذشته است',
            'error' => 'TOO_MANY_REQUESTS',
            'retry_after' => $retryAfter,
        ], 429)->header('Retry-After', $retryAfter);
    }

    protected function addHeaders($response, int $maxAttempts, int $remainingAttempts)
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }
}
