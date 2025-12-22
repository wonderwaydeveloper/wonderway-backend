<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_notifications(): void
    {
        $user = User::factory()->create();
        $fromUser = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'user_id', 'from_user_id'],
                ],
            ]);
    }

    public function test_user_can_view_unread_notifications(): void
    {
        $user = User::factory()->create();
        $fromUser = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $fromUser = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_others_notification_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $fromUser = User::factory()->create();

        $notification = Notification::create([
            'user_id' => $otherUser->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $fromUser = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'follow',
            'notifiable_id' => 2,
            'notifiable_type' => 'App\Models\User',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/mark-all-read');

        $response->assertStatus(200);
        $this->assertEquals(0, $user->notifications()->unread()->count());
    }

    public function test_user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        $fromUser = User::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'from_user_id' => $fromUser->id,
            'type' => 'like',
            'notifiable_id' => 1,
            'notifiable_type' => 'App\Models\Post',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['count' => 1]);
    }

    public function test_guest_cannot_view_notifications(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }
}
