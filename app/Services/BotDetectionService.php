<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BotDetectionService
{
    private array $botUserAgents = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
        'python-requests', 'scrapy', 'selenium', 'phantomjs',
    ];

    private array $suspiciousPatterns = [
        'rapid_requests' => 10, // requests per second
        'no_javascript' => true,
        'no_cookies' => true,
        'suspicious_headers' => true,
        'behavioral_analysis' => true,
    ];

    public function detectBot(Request $request): array
    {
        $score = 0;
        $indicators = [];

        // User Agent Analysis
        $userAgent = $request->userAgent();
        if ($this->isBotUserAgent($userAgent)) {
            $score += 50;
            $indicators[] = 'bot_user_agent';
        }

        // Request Pattern Analysis
        if ($this->hasRapidRequests($request->ip())) {
            $score += 30;
            $indicators[] = 'rapid_requests';
        }

        // Header Analysis
        if ($this->hasSuspiciousHeaders($request)) {
            $score += 20;
            $indicators[] = 'suspicious_headers';
        }

        // Behavioral Analysis
        if ($this->hasSuspiciousBehavior($request)) {
            $score += 25;
            $indicators[] = 'suspicious_behavior';
        }

        // JavaScript Challenge Result
        if (! $this->passedJavaScriptChallenge($request)) {
            $score += 15;
            $indicators[] = 'no_javascript';
        }

        // Fingerprint Analysis
        if ($this->hasKnownBotFingerprint($request)) {
            $score += 40;
            $indicators[] = 'known_bot_fingerprint';
        }

        return [
            'is_bot' => $score >= 70,
            'confidence' => min($score, 100),
            'indicators' => $indicators,
            'action' => $this->determineAction($score),
        ];
    }

    public function challengeBot(Request $request): array
    {
        $challengeType = $this->selectChallengeType($request);

        switch ($challengeType) {
            case 'javascript':
                return $this->createJavaScriptChallenge();
            case 'captcha':
                return $this->createCaptchaChallenge();
            case 'rate_limit':
                return $this->createRateLimitChallenge();
            default:
                return $this->createBasicChallenge();
        }
    }

    private function isBotUserAgent(?string $userAgent): bool
    {
        if (! $userAgent) {
            return true;
        }

        $userAgent = strtolower($userAgent);

        foreach ($this->botUserAgents as $botPattern) {
            if (strpos($userAgent, $botPattern) !== false) {
                return true;
            }
        }

        // Check for missing common browser indicators
        $browserIndicators = ['mozilla', 'webkit', 'chrome', 'firefox', 'safari'];
        $hasIndicator = false;

        foreach ($browserIndicators as $indicator) {
            if (strpos($userAgent, $indicator) !== false) {
                $hasIndicator = true;

                break;
            }
        }

        return ! $hasIndicator;
    }

    private function hasRapidRequests(string $ip): bool
    {
        $key = "bot_requests:{$ip}";
        $requests = Cache::get($key, []);
        $now = time();

        // Remove old requests (older than 10 seconds)
        $requests = array_filter($requests, fn ($time) => $now - $time < 10);

        // Add current request
        $requests[] = $now;
        Cache::put($key, $requests, now()->addMinutes(10));

        // Check if more than 10 requests in 10 seconds
        return count($requests) > 10;
    }

    private function hasSuspiciousHeaders(Request $request): bool
    {
        $headers = $request->headers->all();

        // Missing common headers
        $requiredHeaders = ['accept', 'accept-language', 'accept-encoding'];
        foreach ($requiredHeaders as $header) {
            if (! isset($headers[$header])) {
                return true;
            }
        }

        // Suspicious header values
        $accept = $request->header('accept', '');
        if (strpos($accept, 'text/html') === false && strpos($accept, '*/*') === false) {
            return true;
        }

        // Check for automation tools headers
        $automationHeaders = ['x-requested-with', 'x-automation', 'x-bot'];
        foreach ($automationHeaders as $header) {
            if ($request->hasHeader($header)) {
                return true;
            }
        }

        return false;
    }

    private function hasSuspiciousBehavior(Request $request): bool
    {
        $ip = $request->ip();
        $key = "bot_behavior:{$ip}";
        $behavior = Cache::get($key, [
            'pages_visited' => [],
            'time_spent' => [],
            'interactions' => 0,
        ]);

        // Update behavior data
        $behavior['pages_visited'][] = $request->path();
        $behavior['time_spent'][] = time();

        Cache::put($key, $behavior, now()->addHour());

        // Analyze patterns
        $uniquePages = count(array_unique($behavior['pages_visited']));
        $totalRequests = count($behavior['pages_visited']);

        // Too many requests to same page
        if ($totalRequests > 20 && $uniquePages < 3) {
            return true;
        }

        // No time spent on pages (too fast)
        if (count($behavior['time_spent']) > 5) {
            $avgTime = array_sum(array_map(function ($i) use ($behavior) {
                return isset($behavior['time_spent'][$i + 1])
                    ? $behavior['time_spent'][$i + 1] - $behavior['time_spent'][$i]
                    : 0;
            }, range(0, count($behavior['time_spent']) - 2))) / (count($behavior['time_spent']) - 1);

            if ($avgTime < 2) { // Less than 2 seconds per page
                return true;
            }
        }

        return false;
    }

    private function passedJavaScriptChallenge(Request $request): bool
    {
        return $request->hasHeader('X-JS-Challenge') &&
               $request->header('X-JS-Challenge') === 'passed';
    }

    private function hasKnownBotFingerprint(Request $request): bool
    {
        $fingerprint = $this->generateFingerprint($request);

        return Cache::has("known_bot:{$fingerprint}");
    }

    private function generateFingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->userAgent(),
            $request->header('accept', ''),
            $request->header('accept-language', ''),
            $request->header('accept-encoding', ''),
            $request->ip(),
        ]));
    }

    private function determineAction(int $score): string
    {
        if ($score >= 90) {
            return 'block';
        }
        if ($score >= 70) {
            return 'challenge';
        }
        if ($score >= 50) {
            return 'monitor';
        }

        return 'allow';
    }

    private function selectChallengeType(Request $request): string
    {
        // Select challenge based on request characteristics
        if ($this->hasRapidRequests($request->ip())) {
            return 'rate_limit';
        }

        if (! $this->passedJavaScriptChallenge($request)) {
            return 'javascript';
        }

        return 'captcha';
    }

    private function createJavaScriptChallenge(): array
    {
        $challenge = base64_encode(random_bytes(16));
        $solution = hash('sha256', $challenge);

        return [
            'type' => 'javascript',
            'challenge' => $challenge,
            'expected_solution' => $solution,
            'script' => "
                const challenge = '{$challenge}';
                const solution = CryptoJS.SHA256(challenge).toString();
                fetch('/api/bot-challenge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-JS-Challenge': 'passed'
                    },
                    body: JSON.stringify({solution: solution})
                });
            ",
        ];
    }

    private function createCaptchaChallenge(): array
    {
        return [
            'type' => 'captcha',
            'challenge_url' => '/api/captcha/generate',
            'verify_url' => '/api/captcha/verify',
        ];
    }

    private function createRateLimitChallenge(): array
    {
        return [
            'type' => 'rate_limit',
            'message' => 'Please wait before making another request',
            'retry_after' => 30,
        ];
    }

    private function createBasicChallenge(): array
    {
        return [
            'type' => 'basic',
            'message' => 'Please verify you are human',
            'action' => 'refresh_page',
        ];
    }

    public function markAsBot(Request $request): void
    {
        $fingerprint = $this->generateFingerprint($request);
        Cache::put("known_bot:{$fingerprint}", true, now()->addDays(7));

        Log::channel('security')->info('Bot detected and marked', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fingerprint' => $fingerprint,
        ]);
    }
}
