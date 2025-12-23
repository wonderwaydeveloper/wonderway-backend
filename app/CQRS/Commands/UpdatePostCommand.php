<?php

namespace App\CQRS\Commands;

class UpdatePostCommand implements CommandInterface
{
    public function __construct(
        private string $postId,
        private string $userId,
        private string $content
    ) {
    }

    public function getPayload(): array
    {
        return [
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'content' => $this->content,
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

    public function getContent(): string
    {
        return $this->content;
    }
}