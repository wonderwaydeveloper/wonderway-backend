<?php

namespace App\Http\Middleware;

use App\Services\SpamDetectionService;
use Closure;
use Illuminate\Http\Request;

class SpamDetectionMiddleware
{
    protected $spamDetection;

    public function __construct(SpamDetectionService $spamDetection)
    {
        $this->spamDetection = $spamDetection;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        // Skip spam detection for admins
        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        // Check if user is suspicious
        if ($user && $this->spamDetection->isUserSuspicious($user)) {
            // Apply stricter rate limiting for suspicious users
            $request->merge(['_spam_suspicious' => true]);
        }

        // Pre-check content for obvious spam
        if ($request->has('content')) {
            $content = $request->input('content');

            // Quick spam keyword check
            $spamKeywords = ['spam', 'fake', 'scam', 'اسپم', 'جعلی'];
            foreach ($spamKeywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    return response()->json([
                        'message' => 'محتوای شما حاوی کلمات مشکوک است',
                        'error' => 'SPAM_DETECTED',
                    ], 422);
                }
            }

            // Check for excessive URLs
            if (preg_match_all('/https?:\/\/[^\s]+/', $content) > 2) {
                return response()->json([
                    'message' => 'تعداد لینک در محتوا بیش از حد مجاز است',
                    'error' => 'TOO_MANY_LINKS',
                ], 422);
            }
        }

        return $next($request);
    }
}
