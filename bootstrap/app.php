<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global Security Headers for ALL requests
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\WebApplicationFirewall::class,
            \App\Http\Middleware\BruteForceProtection::class,
            \App\Http\Middleware\AdvancedRateLimit::class,
            \App\Http\Middleware\AdvancedInputValidation::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\PerformanceMonitoring::class,
        ]);

        $middleware->alias([
            'check.reply.permission' => \App\Http\Middleware\CheckReplyPermission::class,
            'advanced.rate.limit' => \App\Http\Middleware\AdvancedRateLimit::class,
            'spam.detection' => \App\Http\Middleware\SpamDetectionMiddleware::class,
            'set.locale' => \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\PostNotFoundException $e) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\UserNotFoundException $e) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\UnauthorizedActionException $e) {
            return $e->render();
        });

        $exceptions->render(function (\App\Exceptions\ValidationException $e) {
            return $e->render();
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Resource not found',
                'message' => 'منبع مورد نظر یافت نشد',
            ], 404);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'error' => 'Unauthenticated',
                'message' => 'لطفا وارد شوید',
            ], 401);
        });
    })->create();
