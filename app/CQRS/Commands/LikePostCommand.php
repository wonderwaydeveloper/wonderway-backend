<?php

namespace App\CQRS\Commands;

class LikePostCommand implements CommandInterface
{
    public function __construct(
        private string $postId,
        private string $userId,
        private bool $isLike = true
    ) {
    }

    public function getPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'is_like' => $this->isLike,
        ];
    }

    public function getPostId(): string
    {
        return $this->postId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function isLike(): bool
    {
        return $this->isLike;
    }
}