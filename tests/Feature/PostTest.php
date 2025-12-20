<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'This is a test post',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'content', 'user', 'created_at'
            ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'This is a test post',
        ]);
    }

    public function test_user_can_create_post_with_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('test.jpg');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'Post with image',
                'image' => $image,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Post with image',
        ]);
    }

    public function test_post_content_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_post_content_cannot_exceed_280_characters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => str_repeat('a', 281),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_can_delete_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_user_cannot_delete_others_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_user_can_like_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson(['liked' => true]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'likeable_id' => $post->id,
            'likeable_type' => Post::class,
        ]);
    }

    public function test_user_can_unlike_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['likes_count' => 1]);
        $post->likes()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/like");

        $response->assertStatus(200)
            ->assertJson(['liked' => false]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'likeable_id' => $post->id,
        ]);
    }

    public function test_user_can_view_timeline(): void
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();
        $user->following()->attach($followedUser->id);

        Post::factory()->create(['user_id' => $followedUser->id, 'published_at' => now()]);
        Post::factory()->create(['user_id' => $user->id, 'published_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/timeline');

        $response->assertStatus(200);
        
        // Check if response has data structure (optimized timeline returns different format)
        $data = $response->json();
        $this->assertTrue(isset($data['data']) || isset($data['optimized']));
    }

    public function test_user_can_create_draft_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'Draft post',
                'is_draft' => true,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Draft post',
            'is_draft' => true,
        ]);
    }

    public function test_user_can_view_own_drafts(): void
    {
        $user = User::factory()->create();
        Post::factory()->create(['user_id' => $user->id, 'is_draft' => true]);
        Post::factory()->create(['user_id' => $user->id, 'is_draft' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/drafts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_guest_cannot_create_post(): void
    {
        $response = $this->postJson('/api/posts', [
            'content' => 'Test post',
        ]);

        $response->assertStatus(401);
    }
}
