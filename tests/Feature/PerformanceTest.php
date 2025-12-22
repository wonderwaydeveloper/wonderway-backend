<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\CacheManagementService;
use App\Services\DatabaseOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_dashboard_accessible()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/performance/dashboard')
            ->assertStatus(200);

        $this->assertArrayHasKey('cache', $response->json());
        $this->assertArrayHasKey('database', $response->json());
        $this->assertArrayHasKey('performance', $response->json());
    }

    public function test_optimized_timeline_works()
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();

        $user->following()->attach($followedUser->id);
        Post::factory()->create(['user_id' => $followedUser->id, 'published_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/performance/timeline/optimized')
            ->assertStatus(200);

        $this->assertArrayHasKey('posts', $response->json());
        $this->assertArrayHasKey('cached', $response->json());
        $this->assertTrue($response->json()['cached']);
    }

    public function test_cache_warmup_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/performance/cache/warmup')
            ->assertStatus(200);

        $this->assertArrayHasKey('message', $response->json());
        $this->assertTrue(Cache::has('hashtags:trending'));
    }

    public function test_cache_clear_works()
    {
        $user = User::factory()->create();

        // Set some cache
        Cache::put('test:key', 'value', 60);
        $this->assertTrue(Cache::has('test:key'));

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/performance/cache/clear', ['type' => 'all'])
            ->assertStatus(200);

        $this->assertFalse(Cache::has('test:key'));
    }

    public function test_database_optimization_service()
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();

        $user->following()->attach($followedUser->id);
        Post::factory()->create(['user_id' => $followedUser->id, 'published_at' => now()]);

        $service = new DatabaseOptimizationService();
        $posts = $service->optimizeTimeline($user->id, 10);

        $this->assertIsArray($posts);
    }

    public function test_cache_management_service()
    {
        $service = new CacheManagementService();

        // Test trending hashtags cache
        $trending = $service->cacheTrendingHashtags();
        $this->assertTrue(Cache::has('hashtags:trending'));

        // Test cache stats
        $stats = $service->getCacheStats();
        $this->assertArrayHasKey('trending_hashtags', $stats);
        $this->assertArrayHasKey('popular_posts', $stats);
    }

    public function test_user_cache_invalidation()
    {
        $user = User::factory()->create();
        $service = new CacheManagementService();

        // Set user cache
        Cache::put("user:stats:{$user->id}", ['posts' => 5], 60);

        // Invalidate cache
        $service->invalidateUserCache($user->id);

        $this->assertFalse(Cache::has("user:stats:{$user->id}"));
    }

    public function test_post_cache_invalidation()
    {
        $post = Post::factory()->create();
        $service = new CacheManagementService();

        // Set post cache
        Cache::put("post:{$post->id}", $post->toArray(), 60);

        // Invalidate cache
        $service->invalidatePostCache($post->id);

        $this->assertFalse(Cache::has("post:{$post->id}"));
    }
}
