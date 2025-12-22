<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Verify2FA
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->two_factor_enabled) {
            if (! $request->session()->get('2fa_verified')) {
                return response()->json([
                    'message' => '2FA verification required',
                    'requires_2fa' => true,
                ], 403);
            }
        }

        return $next($request);
    }
}
