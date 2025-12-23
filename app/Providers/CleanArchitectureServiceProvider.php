<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Service Interfaces
use App\Contracts\Services\PostServiceInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Contracts\Services\NotificationServiceInterface;
use App\Contracts\Services\AuthServiceInterface;

// Service Implementations
use App\Services\PostService;
use App\Services\UserService;
use App\Services\NotificationService;
use App\Services\AuthService;

// Repository Interfaces  
use App\Contracts\Repositories\PostRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\CommentRepositoryInterface;
use App\Contracts\Repositories\HashtagRepositoryInterface;
use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\FollowRepositoryInterface;
use App\Contracts\Repositories\LikeRepositoryInterface;

// Repository Implementations
use App\Repositories\Eloquent\EloquentPostRepository;
use App\Repositories\Eloquent\EloquentUserRepository;
use App\Repositories\Eloquent\EloquentCommentRepository;
use App\Repositories\Eloquent\EloquentHashtagRepository;
use App\Repositories\Eloquent\EloquentMessageRepository;
use App\Repositories\Eloquent\EloquentFollowRepository;
use App\Repositories\Cache\CachedPostRepository;
use App\Repositories\Cache\CachedUserRepository;

class CleanArchitectureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service Bindings
        $this->app->bind(PostServiceInterface::class, PostService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(NotificationServiceInterface::class, NotificationService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);

        $this->app->bind(PostRepositoryInterface::class, \App\Repositories\PostRepository::class);
        $this->app->bind(UserRepositoryInterface::class, \App\Repositories\UserRepository::class);

        // Direct Repository Bindings
        $this->app->bind(CommentRepositoryInterface::class, EloquentCommentRepository::class);
        $this->app->bind(HashtagRepositoryInterface::class, EloquentHashtagRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
        $this->app->bind(FollowRepositoryInterface::class, EloquentFollowRepository::class);
    }
}