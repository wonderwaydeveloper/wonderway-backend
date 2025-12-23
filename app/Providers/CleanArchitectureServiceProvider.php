<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Service Interfaces
use App\Contracts\Services\{PostServiceInterface, UserServiceInterface, NotificationServiceInterface, AuthServiceInterface, FileUploadServiceInterface};

// Service Implementations
use App\Services\{PostService, UserService, NotificationService, AuthService, FileUploadService};

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
        $this->app->bind(FileUploadServiceInterface::class, FileUploadService::class);

        // Repository Bindings with Cache Decorators
        $this->app->bind('App\\Repositories\\Eloquent\\EloquentPostRepository', \App\Repositories\PostRepository::class);
        $this->app->bind(PostRepositoryInterface::class, function ($app) {
            return new CachedPostRepository(
                $app->make('App\\Repositories\\Eloquent\\EloquentPostRepository')
            );
        });

        $this->app->bind('App\\Repositories\\Eloquent\\EloquentUserRepository', \App\Repositories\UserRepository::class);
        $this->app->bind(UserRepositoryInterface::class, function ($app) {
            return new CachedUserRepository(
                $app->make('App\\Repositories\\Eloquent\\EloquentUserRepository')
            );
        });

        // Direct Repository Bindings
        $this->app->bind(CommentRepositoryInterface::class, EloquentCommentRepository::class);
        $this->app->bind(HashtagRepositoryInterface::class, EloquentHashtagRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, EloquentMessageRepository::class);
        $this->app->bind(FollowRepositoryInterface::class, EloquentFollowRepository::class);
        
        // Event Sourcing
        $this->app->singleton(\App\EventSourcing\EventStore::class);
        $this->app->singleton(\App\Domain\Post\Services\PostDomainService::class);
        
        // Command Bus
        $this->app->singleton(\App\CQRS\CommandBus::class, function ($app) {
            $bus = new \App\CQRS\CommandBus($app);
            $bus->register(\App\CQRS\Commands\CreatePostCommand::class, \App\CQRS\Handlers\CreatePostCommandHandler::class);
            $bus->register(\App\CQRS\Commands\UpdatePostCommand::class, \App\CQRS\Handlers\UpdatePostCommandHandler::class);
            $bus->register(\App\CQRS\Commands\LikePostCommand::class, \App\CQRS\Handlers\LikePostCommandHandler::class);
            return $bus;
        });
    }
}