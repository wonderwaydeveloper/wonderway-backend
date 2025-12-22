<?php

namespace Tests\Unit\Domain;

use App\Domain\Post\Entities\PostEntity;
use App\Domain\Post\ValueObjects\PostContent;
use App\Domain\Post\ValueObjects\PostId;
use App\Domain\User\ValueObjects\UserId;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PostEntityTest extends TestCase
{
    public function test_can_create_post_entity()
    {
        $postId = new PostId('test-id');
        $userId = new UserId('user-id');
        $content = new PostContent('Test post content');
        $createdAt = Carbon::now();

        $post = new PostEntity($postId, $userId, $content, $createdAt);

        $this->assertEquals('test-id', $post->getId()->getValue());
        $this->assertEquals('user-id', $post->getUserId()->getValue());
        $this->assertEquals('Test post content', $post->getContent()->getValue());
        $this->assertTrue($post->isPublished());
        $this->assertEquals(0, $post->getLikesCount());
    }

    public function test_can_update_post_content()
    {
        $post = $this->createTestPost();
        $newContent = new PostContent('Updated content');

        $post->updateContent($newContent);

        $this->assertEquals('Updated content', $post->getContent()->getValue());
    }

    public function test_can_increment_likes()
    {
        $post = $this->createTestPost();

        $post->incrementLikes();
        $post->incrementLikes();

        $this->assertEquals(2, $post->getLikesCount());
    }

    public function test_can_publish_and_unpublish()
    {
        $post = $this->createTestPost();

        $post->unpublish();
        $this->assertFalse($post->isPublished());

        $post->publish();
        $this->assertTrue($post->isPublished());
    }

    private function createTestPost(): PostEntity
    {
        return new PostEntity(
            new PostId('test-id'),
            new UserId('user-id'),
            new PostContent('Test content'),
            Carbon::now()
        );
    }
}
