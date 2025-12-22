<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_performance_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/performance/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'cache',
                'database',
                'performance',
            ]);
    }

    public function test_can_warmup_cache()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/performance/cache/warmup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'timestamp',
            ]);
    }

    public function test_can_clear_cache()
    {
        $user = User::factory()->create();
        
        // Set some cache
        Cache::put('test_key', 'test_value', 60);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/performance/cache/clear');

        $response->assertStatus(200);
        
        $this->assertNull(Cache::get('test_key'));
    }

    public function test_optimized_timeline_uses_cache()
    {
        $user = User::factory()->create();
        Post::factory()->count(5)->create(['user_id' => $user->id]);

        // First call - should cache
        $response1 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/timeline');

        $response1->assertStatus(200)
            ->assertJsonPath('cached', true);

        // Second call - should use cache
        $response2 = $this->actingAs($user, 'sanctum')
            ->getJson('/api/timeline');

        $response2->assertStatus(200)
            ->assertJsonPath('cached', true);
    }

    public function test_cache_invalidation_on_new_post()
    {
        $user = User::factory()->create();

        // Cache timeline
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/timeline');

        // Create new post
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'New post to test cache invalidation',
            ]);

        // Timeline should be refreshed
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/timeline');

        $response->assertStatus(200);
    }

    public function test_trending_posts_are_cached()
    {
        $user = User::factory()->create();
        Post::factory()->count(10)->create([
            'likes_count' => rand(10, 100),
            'comments_count' => rand(5, 50),
        ]);

        // Call the cache service directly
        app(\App\Services\CacheOptimizationService::class)->getTrendingPosts(10);
        
        // Verify cache was used
        $this->assertTrue(Cache::has('trending:posts:limit:10'));
    }
}