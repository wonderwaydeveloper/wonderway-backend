<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_notification_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'preferences' => [
                    'email' => ['likes', 'comments', 'follows', 'mentions', 'reposts', 'messages'],
                    'push' => ['likes', 'comments', 'follows', 'mentions', 'reposts', 'messages'],
                    'in_app' => ['likes', 'comments', 'follows', 'mentions', 'reposts', 'messages'],
                ],
            ]);
    }

    public function test_user_can_update_all_preferences(): void
    {
        $user = User::factory()->create();

        $preferences = [
            'email' => [
                'likes' => false,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => false,
                'messages' => true,
            ],
            'push' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => true,
                'messages' => true,
            ],
            'in_app' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'reposts' => true,
                'messages' => true,
            ],
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences', [
                'preferences' => $preferences,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals($preferences, $user->notification_preferences);
    }

    public function test_user_can_disable_all_email_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences/email', [
                'enabled' => false,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $preferences = $user->notification_preferences;

        $this->assertFalse($preferences['email']['likes']);
        $this->assertFalse($preferences['email']['comments']);
        $this->assertFalse($preferences['email']['follows']);
    }

    public function test_user_can_update_specific_notification_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences/push/likes', [
                'enabled' => false,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $preferences = $user->notification_preferences;

        $this->assertFalse($preferences['push']['likes']);
    }

    public function test_preferences_validation_requires_valid_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences/invalid_type', [
                'enabled' => false,
            ]);

        $response->assertStatus(400);
    }

    public function test_preferences_validation_requires_valid_category(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/notifications/preferences/email/invalid_category', [
                'enabled' => false,
            ]);

        $response->assertStatus(400);
    }

    public function test_guest_cannot_access_preferences(): void
    {
        $response = $this->getJson('/api/notifications/preferences');

        $response->assertStatus(401);
    }
}
