<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_subscribe_to_premium()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/subscription/subscribe', [
            'plan' => 'premium',
            'payment_method' => 'stripe_token_123',
        ]);

        $response->assertStatus(201);
    }

    public function test_user_can_view_subscription_status()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => 'premium',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/subscription/current');

        $response->assertStatus(200);
    }

    public function test_user_can_cancel_subscription()
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/subscription/cancel');

        $response->assertStatus(200);
    }
}
