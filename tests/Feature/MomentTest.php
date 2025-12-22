<?php

namespace Tests\Feature;

use App\Models\Moment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MomentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_moment()
    {
        $user = User::factory()->create();
        $posts = Post::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/moments', [
            'title' => 'My First Moment',
            'description' => 'A collection of my best posts',
            'privacy' => 'public',
            'post_ids' => $posts->pluck('id')->toArray(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('moments', [
            'user_id' => $user->id,
            'title' => 'My First Moment',
            'posts_count' => 3,
        ]);
    }

    public function test_user_can_view_public_moments()
    {
        $moments = Moment::factory()->count(3)->public()->create();

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/moments');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_view_private_moment_of_others()
    {
        $moment = Moment::factory()->private()->create();

        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/moments/{$moment->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_add_post_to_moment()
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create(['user_id' => $user->id]);
        $post = Post::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/moments/{$moment->id}/posts", [
                'post_id' => $post->id,
            ]);

        $response->assertStatus(200);
        $this->assertTrue($moment->posts()->where('post_id', $post->id)->exists());
    }

    public function test_user_can_remove_post_from_moment()
    {
        $user = User::factory()->create();
        $moment = Moment::factory()->create(['user_id' => $user->id]);
        $post = Post::factory()->create();

        $moment->addPost($post->id);

        $response = $this->actingAs($user)
            ->deleteJson("/api/moments/{$moment->id}/posts/{$post->id}");

        $response->assertStatus(200);
        $this->assertFalse($moment->posts()->where('post_id', $post->id)->exists());
    }

    public function test_moment_creation_requires_minimum_posts()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/moments', [
            'title' => 'My Moment',
            'privacy' => 'public',
            'post_ids' => [$post->id], // Only 1 post, minimum is 2
        ]);

        $response->assertStatus(422);
    }

    public function test_featured_moments_endpoint()
    {
        // Create test in isolated scope
        $user = User::factory()->create();

        // Create moments with unique titles to avoid conflicts
        $featured1 = Moment::factory()->featured()->public()->create(['title' => 'Featured Test 1']);
        $featured2 = Moment::factory()->featured()->public()->create(['title' => 'Featured Test 2']);
        $normal = Moment::factory()->public()->create(['title' => 'Normal Test', 'is_featured' => false]);

        $response = $this->actingAs($user)->getJson('/api/moments/featured');

        $response->assertStatus(200);

        // Check that we have at least our 2 featured moments
        $data = $response->json('data');
        $featuredTitles = collect($data)->where('is_featured', true)->pluck('title')->toArray();

        $this->assertContains('Featured Test 1', $featuredTitles);
        $this->assertContains('Featured Test 2', $featuredTitles);

        // Ensure all returned items are featured
        foreach ($data as $moment) {
            $this->assertTrue($moment['is_featured'], 'All returned moments should be featured');
        }
    }

    public function test_user_can_view_own_moments()
    {
        $user = User::factory()->create();
        Moment::factory()->count(3)->create(['user_id' => $user->id]);
        Moment::factory()->count(2)->create(); // Other users' moments

        $response = $this->actingAs($user)
            ->getJson('/api/moments/my-moments');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_guest_cannot_create_moment()
    {
        $posts = Post::factory()->count(3)->create();

        $response = $this->postJson('/api/moments', [
            'title' => 'My Moment',
            'privacy' => 'public',
            'post_ids' => $posts->pluck('id')->toArray(),
        ]);

        $response->assertStatus(401);
    }
}
