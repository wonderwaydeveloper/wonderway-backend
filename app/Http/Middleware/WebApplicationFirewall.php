<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WebApplicationFirewall
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check for SQL injection attempts
        if ($this->detectSqlInjection($request)) {
            return response()->json(['error' => 'Blocked by WAF'], 403);
        }

        // Check for XSS attempts
        if ($this->detectXss($request)) {
            return response()->json(['error' => 'Blocked by WAF'], 403);
        }

        return $next($request);
    }

    private function detectSqlInjection(Request $request): bool
    {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\'.*OR.*\'.*=.*\')/i',
            '/(\".*OR.*\".*=.*\")/i',
            '/(\bOR\b.*1.*=.*1)/i',
            '/(\bAND\b.*1.*=.*1)/i',
        ];

        $input = json_encode($request->all());

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    private function detectXss(Request $request): bool
    {
        $patterns = [
            '/<script[^>]*>/i',
            '/<\/script>/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/onmouseover\s*=/i',
            '/alert\s*\(/i',
        ];

        $input = json_encode($request->all());

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }
}
