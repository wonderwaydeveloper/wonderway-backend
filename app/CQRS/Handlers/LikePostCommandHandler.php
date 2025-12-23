<?php

namespace App\CQRS\Handlers;

use App\CQRS\Commands\LikePostCommand;
use App\Domain\Post\Services\PostDomainService;
use App\Domain\Post\ValueObjects\PostId;
use App\Domain\User\ValueObjects\UserId;

class LikePostCommandHandler
{
    public function __construct(
        private PostDomainService $domainService
    ) {
    }

    public function handle(LikePostCommand $command): void
    {
        $postId = new PostId($command->getPostId());
        $userId = new UserId($command->getUserId());
        
        if ($command->isLike()) {
            $this->domainService->likePost($postId, $userId);
        } else {
            $this->domainService->unlikePost($postId, $userId);
        }
    }
}