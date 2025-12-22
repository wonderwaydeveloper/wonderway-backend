<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_post_search_with_filters(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        // Create posts with different characteristics
        $post1 = Post::factory()->create([
            'user_id' => $targetUser->id,
            'content' => 'Test post with image',
            'image' => 'test.jpg',
            'likes_count' => 10,
            'published_at' => now()->subDays(1),
        ]);

        $post2 = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Another test post',
            'likes_count' => 5,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?' . http_build_query([
                'q' => 'test',
                'user_id' => $targetUser->id,
                'has_media' => true,
                'min_likes' => 8,
                'sort' => 'popular',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
            ]);
    }

    public function test_advanced_user_search_with_filters(): void
    {
        $user = User::factory()->create();

        User::factory()->create([
            'name' => 'John Verified',
            'username' => 'johnverified',
        ]);

        User::factory()->create([
            'name' => 'Jane Normal',
            'username' => 'janenormal',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/users?' . http_build_query([
                'q' => 'john',
                'sort' => 'newest',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
            ]);
    }

    public function test_hashtag_search_with_filters(): void
    {
        $user = User::factory()->create();

        Hashtag::factory()->create([
            'name' => 'trending',
            'posts_count' => 100,
        ]);

        Hashtag::factory()->create([
            'name' => 'trendy',
            'posts_count' => 10,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/hashtags?' . http_build_query([
                'q' => 'trend',
                'min_posts' => 50,
                'sort' => 'popular',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'total',
            ]);
    }

    public function test_advanced_search_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/advanced?' . http_build_query([
                'q' => 'test',
                'type' => 'posts',
                'has_media' => true,
                'sort' => 'latest',
            ]));

        $response->assertStatus(200);
    }

    public function test_search_suggestions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/suggestions?' . http_build_query([
                'q' => 'test',
                'type' => 'all',
            ]));

        $response->assertStatus(200);
    }

    public function test_search_validation_errors(): void
    {
        $user = User::factory()->create();

        // Test missing query
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        // Test invalid date range
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?' . http_build_query([
                'q' => 'test',
                'date_from' => '2024-01-01',
                'date_to' => '2023-12-31',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);

        // Test invalid sort option
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?' . http_build_query([
                'q' => 'test',
                'sort' => 'invalid_sort',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_search_with_date_range(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?' . http_build_query([
                'q' => 'test',
                'date_from' => now()->subDays(7)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
    }

    public function test_search_with_hashtags_filter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?' . http_build_query([
                'q' => 'test',
                'hashtags' => ['wonderway', 'social'],
            ]));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_search(): void
    {
        $response = $this->getJson('/api/search/posts?q=test');
        $response->assertStatus(401);

        $response = $this->getJson('/api/search/users?q=test');
        $response->assertStatus(401);

        $response = $this->getJson('/api/search/hashtags?q=test');
        $response->assertStatus(401);
    }
}
