<?php

namespace Tests\Feature;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_live_stream()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/streams', [
                'title' => 'My First Stream',
                'description' => 'Testing live streaming',
                'category' => 'gaming',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'title', 'description', 'stream_key', 'rtmp_url', 'hls_url'
            ]);

        $this->assertDatabaseHas('live_streams', [
            'user_id' => $user->id,
            'title' => 'My First Stream',
        ]);
    }

    public function test_user_can_start_stream()
    {
        $user = User::factory()->create();
        $stream = LiveStream::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/streams/{$stream->id}/start");

        $response->assertOk();
        
        $stream->refresh();
        $this->assertEquals('live', $stream->status);
        $this->assertNotNull($stream->started_at);
    }

    public function test_user_can_join_public_stream()
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'status' => 'live',
            'is_private' => false,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->postJson("/api/streams/{$stream->id}/join");

        $response->assertOk();
        
        $this->assertTrue($stream->viewers()->where('user_id', $viewer->id)->exists());
    }

    public function test_user_cannot_join_private_stream_without_following()
    {
        $streamer = User::factory()->create();
        $viewer = User::factory()->create();
        $stream = LiveStream::factory()->create([
            'user_id' => $streamer->id,
            'status' => 'live',
            'is_private' => true,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->postJson("/api/streams/{$stream->id}/join");

        $response->assertStatus(403);
    }

    public function test_can_view_live_streams()
    {
        $user = User::factory()->create();
        LiveStream::factory()->count(3)->create(['status' => 'live']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/streams');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'user', 'viewer_count']
                ]
            ]);
    }

    public function test_guest_cannot_create_stream()
    {
        $response = $this->postJson('/api/streams', [
            'title' => 'Test Stream',
        ]);

        $response->assertStatus(401);
    }
}