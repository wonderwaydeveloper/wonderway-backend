<?php

namespace App\CQRS\Handlers;

use App\CQRS\Commands\UpdatePostCommand;
use App\Domain\Post\Services\PostDomainService;
use App\Domain\Post\ValueObjects\PostContent;
use App\Domain\Post\ValueObjects\PostId;

class UpdatePostCommandHandler
{
    public function __construct(
        private PostDomainService $domainService
    ) {
    }

    public function handle(UpdatePostCommand $command): void
    {
        $postId = new PostId($command->getPostId());
        $content = new PostContent($command->getContent());
        
        $this->domainService->updatePost($postId, $content);
    }
}