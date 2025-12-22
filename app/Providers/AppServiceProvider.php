<?php

namespace App\Providers;

use App\Events\CommentCreated;
use App\Events\PostLiked;
use App\Events\PostReposted;
use App\Events\UserFollowed;
use App\Listeners\SendCommentNotification;
use App\Listeners\SendFollowNotification;
use App\Listeners\SendLikeNotification;
use App\Listeners\SendRepostNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\NotificationService::class, function ($app) {
            // In testing, use null services to avoid dependencies
            if ($app->environment('testing')) {
                return new \App\Services\NotificationService(null, null);
            }

            return new \App\Services\NotificationService(
                $app->make(\App\Services\EmailService::class),
                $app->make(\App\Services\PushNotificationService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(PostLiked::class, SendLikeNotification::class);
        Event::listen(UserFollowed::class, SendFollowNotification::class);
        Event::listen(PostReposted::class, SendRepostNotification::class);
        Event::listen(CommentCreated::class, SendCommentNotification::class);

        \App\Models\Post::observe(\App\Observers\PostObserver::class);

        // Register Policies
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Moment::class, \App\Policies\MomentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\LiveStream::class, \App\Policies\LiveStreamPolicy::class);
    }
}
