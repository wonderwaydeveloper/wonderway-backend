<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_thread(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/threads', [
                'posts' => [
                    ['content' => 'First post in thread'],
                    ['content' => 'Second post in thread'],
                    ['content' => 'Third post in thread'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'content', 'thread_posts',
            ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'First post in thread',
            'thread_id' => null,
        ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Second post in thread',
            'thread_position' => 1,
        ]);
    }

    public function test_user_can_add_to_existing_thread(): void
    {
        $user = User::factory()->create();
        $mainPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        // Create initial thread post
        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/threads/{$mainPost->id}/add", [
                'content' => 'Adding to existing thread',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Adding to existing thread',
            'thread_id' => $mainPost->id,
            'thread_position' => 2,
        ]);
    }

    public function test_can_view_complete_thread(): void
    {
        $user = User::factory()->create();
        $mainPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        // Create thread posts
        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 1,
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 2,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/threads/{$mainPost->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'thread_root',
                'thread_posts',
                'total_posts',
            ])
            ->assertJson(['total_posts' => 3]);
    }

    public function test_can_get_thread_stats(): void
    {
        $user = User::factory()->create();
        $mainPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/threads/{$mainPost->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_posts',
                'total_likes',
                'total_comments',
                'participants',
                'created_at',
                'last_updated',
            ]);
    }

    public function test_thread_creation_requires_minimum_posts(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/threads', [
                'posts' => [
                    ['content' => 'Only one post'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['posts']);
    }

    public function test_thread_creation_limits_maximum_posts(): void
    {
        $user = User::factory()->create();

        $posts = [];
        for ($i = 0; $i < 26; $i++) {
            $posts[] = ['content' => "Post number {$i}"];
        }

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/threads', ['posts' => $posts]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['posts']);
    }

    public function test_post_shows_thread_information(): void
    {
        $user = User::factory()->create();
        $mainPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$mainPost->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['thread_info']);
    }

    public function test_timeline_shows_only_main_posts(): void
    {
        $user = User::factory()->create();
        $mainPost = Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        // Create thread post (should not appear in timeline)
        Post::factory()->create([
            'user_id' => $user->id,
            'thread_id' => $mainPost->id,
            'thread_position' => 1,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/posts');

        $response->assertStatus(200);

        $posts = $response->json('data');
        $threadPosts = collect($posts)->filter(function ($post) {
            return ! is_null($post['thread_id']);
        });

        $this->assertTrue($threadPosts->isEmpty(), 'Timeline should not show thread posts');
    }

    public function test_guest_cannot_create_thread(): void
    {
        $response = $this->postJson('/api/threads', [
            'posts' => [
                ['content' => 'First post'],
                ['content' => 'Second post'],
            ],
        ]);

        $response->assertStatus(401);
    }
}
