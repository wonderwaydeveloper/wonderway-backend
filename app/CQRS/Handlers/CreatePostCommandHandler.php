<?php

namespace App\CQRS\Handlers;

use App\CQRS\Commands\CreatePostCommand;
use App\Domain\Post\Entities\PostEntity;
use App\Domain\Post\ValueObjects\PostContent;
use App\Domain\Post\ValueObjects\PostId;
use App\Domain\User\ValueObjects\UserId;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreatePostCommandHandler
{
    public function handle(CreatePostCommand $command): PostEntity
    {
        $postId = new PostId(Str::uuid()->toString());
        $userId = new UserId($command->getUserId());
        $content = new PostContent($command->getContent());

        $postEntity = new PostEntity(
            $postId,
            $userId,
            $content,
            Carbon::now(),
            ! $command->isScheduled()
        );

        // Save to database
        $post = Post::create([
            'id' => $postEntity->getId()->getValue(),
            'user_id' => $postEntity->getUserId()->getValue(),
            'content' => $postEntity->getContent()->getValue(),
            'media' => $command->getMedia(),
            'parent_id' => $command->getParentId(),
            'is_published' => $postEntity->isPublished(),
            'scheduled_at' => $command->getScheduledAt(),
            'created_at' => $postEntity->toArray()['created_at'],
        ]);

        return $postEntity;
    }
}
