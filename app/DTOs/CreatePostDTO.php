<?php

namespace App\DTOs;

class CreatePostDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $content,
        public readonly ?string $gifUrl = null,
        public readonly string $replySettings = 'everyone',
        public readonly bool $isDraft = false
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'content' => $this->content,
            'gif_url' => $this->gifUrl,
            'reply_settings' => $this->replySettings,
        ];
    }
}
