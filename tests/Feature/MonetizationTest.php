<?php

namespace Tests\Feature;

use App\Models\User;
use App\Monetization\Models\Advertisement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_advertisement()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/monetization/ads', [
            'title' => 'Test Ad',
            'content' => 'This is a test advertisement',
            'budget' => 100.00,
            'start_date' => now()->addDay()->toISOString(),
            'end_date' => now()->addWeek()->toISOString(),
            'target_audience' => ['all'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('advertisements', [
            'title' => 'Test Ad',
            'advertiser_id' => $user->id,
        ]);
    }

    public function test_can_get_targeted_ads()
    {
        $user = User::factory()->create();
        $advertiser = User::factory()->create();

        Advertisement::factory()->create([
            'advertiser_id' => $advertiser->id,
            'status' => 'active',
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'target_audience' => ['all'],
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/monetization/ads/targeted');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'advertiser'],
            ],
        ]);
    }

    public function test_can_record_ad_click()
    {
        $user = User::factory()->create();
        $ad = Advertisement::factory()->create([
            'status' => 'active',
            'clicks_count' => 0,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("/api/monetization/ads/{$ad->id}/click");

        $response->assertStatus(200);
        $this->assertEquals(1, $ad->fresh()->clicks_count);
    }

    public function test_creator_fund_calculation()
    {
        $creator = User::factory()->create();

        // Create some posts with views for the creator
        $creator->posts()->create([
            'content' => 'Test post',
            'views_count' => 1000,
            'likes_count' => 50,
            'comments_count' => 10,
            'reposts_count' => 5,
        ]);

        $this->actingAs($creator);

        $response = $this->postJson('/api/monetization/creator-fund/calculate-earnings', [
            'month' => now()->month,
            'year' => now()->year,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['month', 'year', 'earnings'],
        ]);
    }
}
