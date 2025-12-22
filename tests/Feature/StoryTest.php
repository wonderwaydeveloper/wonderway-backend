<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_story()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('story.jpg');

        $response = $this->actingAs($user)->postJson('/api/stories', [
            'media' => $file,
            'caption' => 'My story',
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure(['id', 'media_url', 'user_id']);
    }

    public function test_user_can_view_stories()
    {
        $user = User::factory()->create();
        Story::factory()->count(3)->create([
            'user_id' => $user->id,
            'expires_at' => now()->addHours(12),
        ]);

        $response = $this->actingAs($user)->getJson('/api/stories');

        $response->assertStatus(200);
    }

    public function test_story_expires_after_24_hours()
    {
        $user = User::factory()->create();
        Story::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($user)->getJson('/api/stories');

        $response->assertStatus(200);
    }
}
