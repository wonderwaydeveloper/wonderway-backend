<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_device(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/push/register', [
                'token' => 'test_device_token_123',
                'device_type' => 'android',
                'device_name' => 'Samsung Galaxy S21',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'device_id']);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'test_device_token_123',
            'device_type' => 'android',
            'active' => true,
        ]);
    }

    public function test_user_can_unregister_device(): void
    {
        $user = User::factory()->create();
        $token = 'test_device_token_123';

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'device_type' => 'android',
            'active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/push/unregister/{$token}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => $token,
            'active' => false,
        ]);
    }

    public function test_user_can_get_devices(): void
    {
        $user = User::factory()->create();

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token1',
            'device_type' => 'android',
            'active' => true,
        ]);

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => 'token2',
            'device_type' => 'ios',
            'active' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/push/devices');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'devices');
    }

    public function test_device_registration_requires_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/push/register', [
                'device_type' => 'android',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_device_registration_requires_valid_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/push/register', [
                'token' => 'test_token',
                'device_type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_type']);
    }

    public function test_guest_cannot_register_device(): void
    {
        $response = $this->postJson('/api/push/register', [
            'token' => 'test_token',
            'device_type' => 'android',
        ]);

        $response->assertStatus(401);
    }
}
