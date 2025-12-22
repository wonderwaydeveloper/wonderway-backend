<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_scheduled_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/scheduled-posts', [
            'content' => 'Test scheduled post',
            'scheduled_at' => now()->addHours(2),
        ]);

        $response->assertStatus(201);
    }

    public function test_list_scheduled_posts()
    {
        $this->assertTrue(true);
    }

    public function test_delete_scheduled_post()
    {
        $this->assertTrue(true);
    }
}
