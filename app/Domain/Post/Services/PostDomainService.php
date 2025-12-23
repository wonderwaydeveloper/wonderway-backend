<?php

namespace App\Domain\Post\Services;

use App\Domain\Post\Entities\PostEntity;
use App\Domain\Post\Repositories\PostDomainRepositoryInterface;
use App\Domain\Post\ValueObjects\PostContent;
use App\Domain\Post\ValueObjects\PostId;
use App\Domain\User\ValueObjects\UserId;
use App\EventSourcing\EventStore;
use App\EventSourcing\Events\PostCreatedEvent;
use App\EventSourcing\Events\PostLikedEvent;
use App\EventSourcing\Events\PostUnlikedEvent;
use App\EventSourcing\Events\PostUpdatedEvent;
use Carbon\Carbon;

class PostDomainService
{
    public function __construct(
        private PostDomainRepositoryInterface $repository,
        private EventStore $eventStore
    ) {
    }

    public function createPost(UserId $userId, PostContent $content): PostEntity
    {
        $postId = new PostId(\Illuminate\Support\Str::uuid()->toString());
        $post = new PostEntity($postId, $userId, $content, Carbon::now());
        
        $this->repository->save($post);
        
        $event = new PostCreatedEvent($postId->getValue(), [
            'user_id' => $userId->getValue(),
            'content' => $content->getValue(),
        ]);
        $this->eventStore->append($event);
        
        return $post;
    }

    public function updatePost(PostId $postId, PostContent $content): void
    {
        $post = $this->repository->findById($postId);
        if (!$post) {
            throw new \Exception('Post not found');
        }
        
        $post->updateContent($content);
        $this->repository->save($post);
        
        $event = new PostUpdatedEvent($postId->getValue(), [
            'content' => $content->getValue(),
        ]);
        $this->eventStore->append($event);
    }

    public function likePost(PostId $postId, UserId $userId): void
    {
        $post = $this->repository->findById($postId);
        if (!$post) {
            throw new \Exception('Post not found');
        }
        
        $post->incrementLikes();
        $this->repository->save($post);
        
        $event = new PostLikedEvent($postId->getValue(), [
            'user_id' => $userId->getValue(),
        ]);
        $this->eventStore->append($event);
    }

    public function unlikePost(PostId $postId, UserId $userId): void
    {
        $post = $this->repository->findById($postId);
        if (!$post) {
            throw new \Exception('Post not found');
        }
        
        $post->decrementLikes();
        $this->repository->save($post);
        
        $event = new PostUnlikedEvent($postId->getValue(), [
            'user_id' => $userId->getValue(),
        ]);
        $this->eventStore->append($event);
    }
}