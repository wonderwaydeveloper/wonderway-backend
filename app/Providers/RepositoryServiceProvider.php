<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\PostServiceInterface;
use App\Contracts\PostRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Services\PostService;
use App\Repositories\PostRepository;
use App\Repositories\UserRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind Repository Interfaces
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        
        // Bind Service Interfaces
        $this->app->bind(PostServiceInterface::class, PostService::class);
        
        // Singleton Services
        $this->app->singleton(\App\Services\SecurityEventLogger::class);
        $this->app->singleton(\App\Services\DataEncryptionService::class);
        
        // CQRS Handlers
        $this->app->bind(\App\CQRS\Handlers\CreatePostCommandHandler::class);
        
        // Design Patterns
        $this->app->singleton(\App\Patterns\Factory\NotificationFactory::class);
        $this->app->bind(\App\Patterns\Strategy\ContentModerationContext::class);
        
        // Monetization Services
        $this->app->singleton(\App\Monetization\Services\AdvertisementService::class);
        $this->app->singleton(\App\Monetization\Services\CreatorFundService::class);
        
        // Enhancement Services
        $this->app->singleton(\App\Services\ConnectionManagementService::class);
        $this->app->singleton(\App\Services\RichNotificationService::class);
        $this->app->singleton(\App\Services\EmailAnalyticsService::class);
    }

    public function boot(): void
    {
        //
    }
}