<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $post->user);
        $this->assertEquals($user->id, $post->user->id);
    }

    public function test_post_has_likes_relationship(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $post->likes()->create(['user_id' => $user->id]);

        $this->assertCount(1, $post->likes);
    }

    public function test_is_liked_by_method(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->assertFalse($post->isLikedBy($user->id));

        $post->likes()->create(['user_id' => $user->id]);

        $this->assertTrue($post->isLikedBy($user->id));
    }

    public function test_published_scope(): void
    {
        Post::factory()->create(['is_draft' => false]);
        Post::factory()->create(['is_draft' => true]);

        $publishedPosts = Post::published()->get();

        $this->assertCount(1, $publishedPosts);
        $this->assertFalse($publishedPosts->first()->is_draft);
    }

    public function test_drafts_scope(): void
    {
        Post::factory()->create(['is_draft' => false]);
        Post::factory()->create(['is_draft' => true]);

        $draftPosts = Post::drafts()->get();

        $this->assertCount(1, $draftPosts);
        $this->assertTrue($draftPosts->first()->is_draft);
    }

    public function test_sync_hashtags_method(): void
    {
        $post = Post::factory()->create([
            'content' => 'Test post with #laravel and #php hashtags',
        ]);

        $post->syncHashtags();

        $this->assertCount(2, $post->hashtags);
        $this->assertTrue($post->hashtags->pluck('name')->contains('laravel'));
        $this->assertTrue($post->hashtags->pluck('name')->contains('php'));
    }

    public function test_post_has_comments_relationship(): void
    {
        $post = Post::factory()->create();
        $post->comments()->create([
            'user_id' => User::factory()->create()->id,
            'content' => 'Test comment',
        ]);

        $this->assertCount(1, $post->comments);
    }
}
