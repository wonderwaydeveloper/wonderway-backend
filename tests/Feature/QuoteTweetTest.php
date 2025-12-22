<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTweetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_quote_tweet(): void
    {
        $user = User::factory()->create();
        $originalPost = Post::factory()->create(['published_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$originalPost->id}/quote", [
                'content' => 'This is my quote tweet comment',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'content', 'quoted_post_id', 'user', 'quoted_post',
            ]);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'This is my quote tweet comment',
            'quoted_post_id' => $originalPost->id,
        ]);
    }

    public function test_quote_tweet_content_is_required(): void
    {
        $user = User::factory()->create();
        $originalPost = Post::factory()->create(['published_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$originalPost->id}/quote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_can_view_quotes_of_post(): void
    {
        $user = User::factory()->create();
        $originalPost = Post::factory()->create(['published_at' => now()]);

        // Create quote tweets
        Post::factory()->create([
            'quoted_post_id' => $originalPost->id,
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'quoted_post_id' => $originalPost->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$originalPost->id}/quotes");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_post_shows_quotes_count(): void
    {
        $user = User::factory()->create();
        $originalPost = Post::factory()->create(['published_at' => now()]);

        Post::factory()->create([
            'quoted_post_id' => $originalPost->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$originalPost->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['quotes_count']);
    }

    public function test_guest_cannot_quote_tweet(): void
    {
        $originalPost = Post::factory()->create(['published_at' => now()]);

        $response = $this->postJson("/api/posts/{$originalPost->id}/quote", [
            'content' => 'Test quote',
        ]);

        $response->assertStatus(401);
    }
}
