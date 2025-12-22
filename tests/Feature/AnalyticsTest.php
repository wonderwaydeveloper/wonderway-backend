<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_track_analytics_event()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/analytics/track', [
                'event_type' => 'post_view',
                'entity_type' => 'post',
                'entity_id' => $post->id,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('analytics_events', [
            'user_id' => $user->id,
            'event_type' => 'post_view',
            'entity_type' => 'post',
            'entity_id' => $post->id,
        ]);
    }

    public function test_can_get_user_analytics_dashboard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'dashboard' => [
                    'today',
                    'week',
                    'month',
                    'growth',
                ],
            ]);
    }

    public function test_can_get_detailed_user_analytics()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/user?period=30d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'analytics' => [
                    'profile_views',
                    'post_performance',
                    'engagement_metrics',
                    'follower_growth',
                    'top_posts',
                ],
            ]);
    }

    public function test_can_get_post_analytics()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/analytics/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'post_analytics' => [
                    'views',
                    'engagement',
                    'demographics',
                    'timeline',
                ],
            ]);
    }

    public function test_cannot_view_others_post_analytics()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/analytics/posts/{$post->id}");

        $response->assertStatus(403);
    }

    public function test_analytics_event_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/analytics/track', [
                'event_type' => 'invalid_event',
                'entity_type' => 'post',
                'entity_id' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);
    }

    public function test_guest_can_track_events()
    {
        $post = Post::factory()->create();

        $response = $this->postJson('/api/analytics/track', [
            'event_type' => 'post_view',
            'entity_type' => 'post',
            'entity_id' => $post->id,
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('analytics_events', [
            'user_id' => null,
            'event_type' => 'post_view',
            'entity_type' => 'post',
            'entity_id' => $post->id,
        ]);
    }
}