<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Enhanced Security Headers
        $this->setSecurityHeaders($response);

        // Log security events
        $this->logSecurityEvent($request);

        return $response;
    }

    private function setSecurityHeaders($response)
    {
        // Prevent clickjacking
        $response->header('X-Frame-Options', 'DENY');

        // Prevent MIME type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Enhanced XSS protection
        $response->header('X-XSS-Protection', '1; mode=block');

        // Strict Content Security Policy
        $csp = "default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
             . "img-src 'self' data: https: blob:; "
             . "font-src 'self' data: https://fonts.gstatic.com; "
             . "connect-src 'self' wss: https:; "
             . "media-src 'self' blob:; "
             . "object-src 'none'; "
             . "base-uri 'self'; "
             . "form-action 'self'; "
             . "frame-ancestors 'none'";
        $response->header('Content-Security-Policy', $csp);

        // Enhanced Referrer Policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Comprehensive Permissions Policy
        $permissions = 'geolocation=(), microphone=(), camera=(), '
                     . 'payment=(), usb=(), magnetometer=(), '
                     . 'gyroscope=(), speaker=(), vibrate=(), '
                     . 'fullscreen=(self), sync-xhr=()';
        $response->header('Permissions-Policy', $permissions);

        // Enhanced HSTS
        $response->header('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');

        // Additional Security Headers
        $response->header('X-Permitted-Cross-Domain-Policies', 'none');
        $response->header('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        $response->header('Cross-Origin-Resource-Policy', 'same-origin');

        // Remove server information
        $response->header('Server', 'WonderWay');
        $response->header('X-Powered-By', null);
    }

    private function logSecurityEvent(Request $request)
    {
        // Log suspicious requests
        if ($this->isSuspiciousRequest($request)) {
            \Log::warning('Suspicious request detected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'timestamp' => now(),
            ]);
        }
    }

    private function isSuspiciousRequest(Request $request): bool
    {
        $suspiciousPatterns = [
            '/\.\.\//i',  // Directory traversal
            '/<script/i', // XSS attempts
            '/union.*select/i', // SQL injection
            '/eval\(/i',  // Code injection
        ];

        $fullUrl = $request->fullUrl();
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $fullUrl)) {
                return true;
            }
        }

        return false;
    }
}
