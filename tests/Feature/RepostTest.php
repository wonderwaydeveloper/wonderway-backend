<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_repost(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/repost");

        $response->assertStatus(201)
            ->assertJson(['reposted' => true]);

        $this->assertDatabaseHas('reposts', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_can_repost_with_quote(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/repost", [
                'quote' => 'Great post!',
            ]);

        $response->assertStatus(201)
            ->assertJson(['reposted' => true]);

        $this->assertDatabaseHas('reposts', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'quote' => 'Great post!',
        ]);
    }

    public function test_user_can_unrepost(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $user->reposts()->create(['post_id' => $post->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/repost");

        $response->assertStatus(200)
            ->assertJson(['reposted' => false]);

        $this->assertDatabaseMissing('reposts', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_quote_cannot_exceed_280_characters(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/repost", [
                'quote' => str_repeat('a', 281),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quote']);
    }

    public function test_user_can_view_own_reposts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $user->reposts()->create(['post_id' => $post->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/my-reposts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'post_id'],
                ],
            ]);
    }

    public function test_guest_cannot_repost(): void
    {
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/repost");

        $response->assertStatus(401);
    }
}
