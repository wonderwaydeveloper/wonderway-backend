<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class BruteForceProtection
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') && $request->is('api/login')) {
            $key = 'login_attempts:' . $request->ip();
            $attempts = Redis::get($key) ?? 0;

            if ($attempts >= 5) {
                return response()->json([
                    'error' => 'Account temporarily locked due to too many failed attempts',
                ], 423);
            }

            $response = $next($request);

            // If login failed, increment attempts
            if ($response->status() === 401) {
                Redis::incr($key);
                Redis::expire($key, 900); // 15 minutes
            } elseif ($response->status() === 200) {
                // Clear attempts on successful login
                Redis::del($key);
            }

            return $response;
        }

        return $next($request);
    }
}
