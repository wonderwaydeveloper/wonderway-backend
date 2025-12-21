<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConversionTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_track_conversion_event()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/conversions/track', [
                'event_type' => 'signup',
                'event_data' => ['source' => 'organic'],
                'conversion_value' => 10.50,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('conversion_metrics', [
            'user_id' => $user->id,
            'event_type' => 'signup',
            'conversion_type' => 'registration',
        ]);
    }

    public function test_can_get_conversion_funnel()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/conversions/funnel?days=7');

        $response->assertOk()
            ->assertJsonStructure([
                'visitors',
                'signups',
                'active_users',
                'premium_subscriptions',
                'conversion_rates'
            ]);
    }

    public function test_can_get_conversions_by_source()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/conversions/by-source');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['source', 'conversions', 'total_value']
            ]);
    }

    public function test_can_get_user_journey()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/conversions/user-journey');

        $response->assertOk()
            ->assertJsonStructure([
                '*' => ['event', 'timestamp', 'data', 'value']
            ]);
    }

    public function test_conversion_service_determines_correct_type()
    {
        $user = User::factory()->create();
        $service = new ConversionTrackingService();
        
        $service->track('signup', $user->id, [], 0);
        $service->track('post_create', $user->id, [], 0);
        $service->track('subscription', $user->id, [], 9.99);

        $this->assertDatabaseHas('conversion_metrics', [
            'event_type' => 'signup',
            'conversion_type' => 'registration',
        ]);

        $this->assertDatabaseHas('conversion_metrics', [
            'event_type' => 'post_create',
            'conversion_type' => 'engagement',
        ]);

        $this->assertDatabaseHas('conversion_metrics', [
            'event_type' => 'subscription',
            'conversion_type' => 'monetization',
        ]);
    }

    public function test_guest_cannot_access_conversion_data()
    {
        $response = $this->getJson('/api/conversions/funnel');
        $response->assertStatus(401);
    }
}