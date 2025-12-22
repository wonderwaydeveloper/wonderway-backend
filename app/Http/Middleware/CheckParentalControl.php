<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckParentalControl
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_child) {
            return $next($request);
        }

        $control = $user->parentalControl;

        if (! $control) {
            return $next($request);
        }

        if ($control->usage_start_time && $control->usage_end_time) {
            $now = now()->format('H:i');
            if ($now < $control->usage_start_time || $now > $control->usage_end_time) {
                return response()->json([
                    'message' => 'خارج از ساعات مجاز استفاده',
                ], 403);
            }
        }

        if ($request->is('api/posts') && $request->isMethod('post')) {
            $todayPosts = $user->posts()->whereDate('created_at', today())->count();
            if ($todayPosts >= $control->daily_post_limit) {
                return response()->json([
                    'message' => 'به حد مجاز پست روزانه رسیدهاید',
                ], 403);
            }
        }

        return $next($request);
    }
}
