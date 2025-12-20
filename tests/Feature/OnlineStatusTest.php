<?php

namespace Tests\Feature;

use App\Events\UserOnlineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_online_status()
    {
        Event::fake();
        
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/realtime/status', [
                'status' => 'online'
            ])
            ->assertStatus(200)
            ->assertJson(['status' => 'updated']);

        Event::assertDispatched(UserOnlineStatus::class);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_online' => true
        ]);
    }

    public function test_user_can_set_offline_status()
    {
        $user = User::factory()->create(['is_online' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/realtime/status', [
                'status' => 'offline'
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_online' => false
        ]);
    }

    public function test_can_get_online_users()
    {
        $onlineUser = User::factory()->create([
            'is_online' => true,
            'last_seen_at' => now()
        ]);
        
        $offlineUser = User::factory()->create([
            'is_online' => false,
            'last_seen_at' => now()->subHours(1)
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/realtime/online-users')
            ->assertStatus(200);

        $this->assertCount(1, $response->json());
        $this->assertEquals($onlineUser->id, $response->json()[0]['id']);
    }

    public function test_online_status_event_structure()
    {
        $user = User::factory()->create();
        
        $event = new UserOnlineStatus($user->id, 'online');
        
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(\Illuminate\Broadcasting\PresenceChannel::class, $channels[0]);
        
        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('user_id', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);
    }

    public function test_invalid_status_rejected()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/realtime/status', [
                'status' => 'invalid_status'
            ])
            ->assertStatus(422);
    }
}