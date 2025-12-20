<?php

namespace Tests\Feature;

use App\Events\PostInteraction;
use App\Events\PostPublished;
use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealTimeTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_published_event_broadcasts()
    {
        Event::fake([PostPublished::class]);
        
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'New real-time post!'
            ])
            ->assertStatus(201);

        Event::assertDispatched(PostPublished::class);
    }

    public function test_post_interaction_broadcasts_on_like()
    {
        Event::fake([PostInteraction::class]);
        
        $postOwner = User::factory()->create();
        $liker = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postOwner->id]);

        $this->actingAs($liker, 'sanctum')
            ->postJson("/api/posts/{$post->id}/like")
            ->assertStatus(200);

        Event::assertDispatched(PostInteraction::class, function ($event) use ($post) {
            return $event->post->id === $post->id && $event->type === 'like';
        });
    }

    public function test_post_interaction_broadcasts_on_comment()
    {
        Event::fake([PostInteraction::class]);
        
        $postOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postOwner->id]);

        $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [
                'content' => 'Great post!'
            ])
            ->assertStatus(201);

        Event::assertDispatched(PostInteraction::class, function ($event) use ($post) {
            return $event->post->id === $post->id && $event->type === 'comment';
        });
    }

    public function test_live_timeline_endpoint()
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();
        
        $user->following()->attach($followedUser->id);
        
        $post = Post::factory()->create([
            'user_id' => $followedUser->id,
            'published_at' => now()
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/realtime/timeline')
            ->assertStatus(200);

        $this->assertArrayHasKey('posts', $response->json());
        $this->assertArrayHasKey('following_ids', $response->json());
        $this->assertArrayHasKey('channels', $response->json());
    }

    public function test_post_updates_endpoint()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/realtime/posts/{$post->id}")
            ->assertStatus(200);

        $this->assertArrayHasKey('post', $response->json());
        $this->assertArrayHasKey('is_liked', $response->json());
        $this->assertArrayHasKey('channel', $response->json());
    }

    public function test_post_published_event_structure()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $post->load('user');

        $event = new PostPublished($post);
        
        $channels = $event->broadcastOn();
        $this->assertCount(2, $channels);
        
        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('id', $broadcastData);
        $this->assertArrayHasKey('content', $broadcastData);
        $this->assertArrayHasKey('user', $broadcastData);
        $this->assertArrayHasKey('likes_count', $broadcastData);
    }

    public function test_post_interaction_event_structure()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $event = new PostInteraction($post, 'like', $user, ['liked' => true]);
        
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        
        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('post_id', $broadcastData);
        $this->assertArrayHasKey('type', $broadcastData);
        $this->assertArrayHasKey('user', $broadcastData);
        $this->assertArrayHasKey('data', $broadcastData);
    }
}