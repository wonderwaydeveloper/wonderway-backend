<?php

namespace App\CQRS\Commands;

class CreatePostCommand implements CommandInterface
{
    public function __construct(
        private string $userId,
        private string $content,
        private ?array $media = null,
        private ?string $parentId = null,
        private bool $isScheduled = false,
        private ?\DateTime $scheduledAt = null
    ) {
    }

    public function getPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'content' => $this->content,
            'media' => $this->media,
            'parent_id' => $this->parentId,
            'is_scheduled' => $this->isScheduled,
            'scheduled_at' => $this->scheduledAt,
        ];
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getMedia(): ?array
    {
        return $this->media;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function isScheduled(): bool
    {
        return $this->isScheduled;
    }

    public function getScheduledAt(): ?\DateTime
    {
        return $this->scheduledAt;
    }
}
