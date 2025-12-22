<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_bookmark_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/bookmark");

        $response->assertStatus(200)
            ->assertJson(['bookmarked' => true]);

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_can_unbookmark_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $user->bookmarks()->create(['post_id' => $post->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/bookmark");

        $response->assertStatus(200)
            ->assertJson(['bookmarked' => false]);

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_can_view_bookmarks(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $user->bookmarks()->create(['post_id' => $post->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookmarks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'post_id', 'user_id'],
                ],
            ]);
    }

    public function test_guest_cannot_bookmark_post(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/bookmark");

        $response->assertStatus(401);
    }

    public function test_guest_cannot_view_bookmarks(): void
    {
        $response = $this->getJson('/api/bookmarks');

        $response->assertStatus(401);
    }
}
