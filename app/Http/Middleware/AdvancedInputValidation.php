<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdvancedInputValidation
{
    public function handle(Request $request, Closure $next)
    {
        // Check for XSS patterns BEFORE sanitizing
        if ($this->detectXss($request)) {
            return response()->json([
                'message' => 'محتوای مشکوک شناسایی شد',
                'error' => 'SUSPICIOUS_CONTENT',
            ], 400);
        }

        // Check for SQL injection patterns
        if ($this->detectSqlInjection($request)) {
            return response()->json([
                'message' => 'درخواست نامعتبر شناسایی شد',
                'error' => 'INVALID_REQUEST',
            ], 400);
        }

        // Sanitize all string inputs AFTER detection
        $this->sanitizeInputs($request);

        return $next($request);
    }

    private function sanitizeInputs(Request $request)
    {
        $input = $request->all();
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = trim($value);
                $value = strip_tags($value, '<p><br><strong><em>');
            }
        });
        $request->merge($input);
    }

    private function detectSqlInjection(Request $request): bool
    {
        $patterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bDELETE\b|\bUPDATE\b|\bDROP\b)/i',
            '/(\bOR\b|\bAND\b)\s+\d+\s*=\s*\d+/i',
            '/[\'";].*(\bOR\b|\bAND\b)/i',
            '/\b(exec|execute|sp_|xp_)\b/i',
        ];

        foreach ($request->all() as $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function detectXss(Request $request): bool
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
        ];

        foreach ($request->all() as $value) {
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
