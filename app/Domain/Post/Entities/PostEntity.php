<?php

namespace App\Domain\Post\Entities;

use App\Domain\Post\ValueObjects\PostContent;
use App\Domain\Post\ValueObjects\PostId;
use App\Domain\User\ValueObjects\UserId;
use Carbon\Carbon;

class PostEntity
{
    private PostId $id;
    private UserId $userId;
    private PostContent $content;
    private Carbon $createdAt;
    private ?Carbon $updatedAt;
    private bool $isPublished;
    private int $likesCount;
    private int $repostsCount;
    private int $commentsCount;

    public function __construct(
        PostId $id,
        UserId $userId,
        PostContent $content,
        Carbon $createdAt,
        bool $isPublished = true
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->content = $content;
        $this->createdAt = $createdAt;
        $this->isPublished = $isPublished;
        $this->likesCount = 0;
        $this->repostsCount = 0;
        $this->commentsCount = 0;
    }

    public function getId(): PostId
    {
        return $this->id;
    }

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getContent(): PostContent
    {
        return $this->content;
    }

    public function updateContent(PostContent $content): void
    {
        $this->content = $content;
        $this->updatedAt = Carbon::now();
    }

    public function publish(): void
    {
        $this->isPublished = true;
    }

    public function unpublish(): void
    {
        $this->isPublished = false;
    }

    public function incrementLikes(): void
    {
        $this->likesCount++;
    }

    public function decrementLikes(): void
    {
        $this->likesCount = max(0, $this->likesCount - 1);
    }

    public function incrementReposts(): void
    {
        $this->repostsCount++;
    }

    public function incrementComments(): void
    {
        $this->commentsCount++;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function getLikesCount(): int
    {
        return $this->likesCount;
    }

    public function getRepostsCount(): int
    {
        return $this->repostsCount;
    }

    public function getCommentsCount(): int
    {
        return $this->commentsCount;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->getValue(),
            'user_id' => $this->userId->getValue(),
            'content' => $this->content->getValue(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'is_published' => $this->isPublished,
            'likes_count' => $this->likesCount,
            'reposts_count' => $this->repostsCount,
            'comments_count' => $this->commentsCount,
        ];
    }
}