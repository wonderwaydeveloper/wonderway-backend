<?php

namespace Tests\Feature;

use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Services\TrendingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendingTest extends TestCase
{
    use RefreshDatabase;

    private $trendingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trendingService = app(TrendingService::class);
    }

    public function test_can_get_trending_hashtags(): void
    {
        $user = User::factory()->create();

        // Create hashtags with different activity levels
        $hashtag1 = Hashtag::factory()->create(['name' => 'trending1', 'posts_count' => 50]);
        $hashtag2 = Hashtag::factory()->create(['name' => 'trending2', 'posts_count' => 30]);

        // Create recent posts with hashtags
        $post1 = Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now()->subHours(2),
            'likes_count' => 20,
        ]);
        $post2 = Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now()->subHours(1),
            'likes_count' => 15,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/hashtags?limit=5&timeframe=24');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'limit',
                    'timeframe_hours',
                    'generated_at',
                ],
            ]);
    }

    public function test_can_get_trending_posts(): void
    {
        $user = User::factory()->create();

        // Create posts with different engagement levels
        Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now()->subHours(2),
            'likes_count' => 50,
            'comments_count' => 10,
        ]);

        Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now()->subHours(1),
            'likes_count' => 30,
            'comments_count' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/posts?limit=10&timeframe=24');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'limit',
                    'timeframe_hours',
                    'generated_at',
                ],
            ]);
    }

    public function test_can_get_trending_users(): void
    {
        $user = User::factory()->create();

        // Create users with recent activity
        $activeUser1 = User::factory()->create();
        $activeUser2 = User::factory()->create();

        // Create recent posts for active users
        Post::factory()->create([
            'user_id' => $activeUser1->id,
            'published_at' => now()->subHours(2),
            'likes_count' => 25,
        ]);

        Post::factory()->create([
            'user_id' => $activeUser2->id,
            'published_at' => now()->subHours(1),
            'likes_count' => 20,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/users?limit=5&timeframe=168');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'limit',
                    'timeframe_hours',
                    'generated_at',
                ],
            ]);
    }

    public function test_can_get_personalized_trending(): void
    {
        $user = User::factory()->create();

        // Create posts for personalized trending
        Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now()->subHours(2),
            'likes_count' => 15,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/personalized?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'limit',
                    'user_id',
                    'generated_at',
                ],
            ]);
    }

    public function test_can_get_trend_velocity(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/trending/velocity/hashtag/{$hashtag->id}?hours=6");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'id',
                'velocity',
                'hours_analyzed',
                'interpretation',
                'generated_at',
            ]);
    }

    public function test_can_get_all_trending_content(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'hashtags',
                'posts',
                'users',
                'generated_at',
            ]);
    }

    public function test_can_get_trending_stats(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_trending_hashtags',
                'total_trending_posts',
                'total_trending_users',
                'cache_status',
                'last_updated',
            ]);
    }

    public function test_can_refresh_trending_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/trending/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'result' => [
                    'hashtags_updated',
                    'posts_updated',
                    'users_updated',
                    'timestamp',
                ],
            ]);
    }

    public function test_trending_validation_errors(): void
    {
        $user = User::factory()->create();

        // Test invalid limit
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/hashtags?limit=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        // Test invalid timeframe
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/posts?timeframe=200');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['timeframe']);

        // Test invalid velocity type
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/trending/velocity/invalid/1');

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid type']);
    }

    public function test_hashtag_suggestions(): void
    {
        $user = User::factory()->create();

        // Create some hashtags
        Hashtag::factory()->create(['posts_count' => 50]);
        Hashtag::factory()->create(['posts_count' => 30]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/hashtags/suggestions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name', 'slug', 'posts_count'],
            ]);
    }

    public function test_enhanced_hashtag_show(): void
    {
        $user = User::factory()->create();
        $hashtag = Hashtag::factory()->create();

        // Create posts for the hashtag
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'published_at' => now(),
        ]);
        $hashtag->posts()->attach($post->id);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/hashtags/{$hashtag->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'hashtag',
                'posts',
                'trending_info' => [
                    'velocity',
                    'is_trending',
                    'trend_direction',
                ],
            ]);
    }

    public function test_guest_cannot_access_trending(): void
    {
        $response = $this->getJson('/api/trending/hashtags');
        $response->assertStatus(401);

        $response = $this->getJson('/api/trending/posts');
        $response->assertStatus(401);

        $response = $this->getJson('/api/trending/users');
        $response->assertStatus(401);
    }
}
