<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_video_with_post()
    {
        $user = User::factory()->create();
        
        $video = UploadedFile::fake()->create('test-video.mp4', 1024, 'video/mp4');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'Test post with video',
                'video' => $video,
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'content' => 'Test post with video',
        ]);

        // In testing environment, video processing completes immediately
        $this->assertDatabaseHas('videos', [
            'encoding_status' => 'completed',
        ]);
    }

    public function test_video_validation_rejects_large_files()
    {
        $user = User::factory()->create();
        
        // Create a file larger than 100MB
        $video = UploadedFile::fake()->create('large-video.mp4', 102401, 'video/mp4');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'Test post with large video',
                'video' => $video,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    public function test_video_validation_rejects_invalid_formats()
    {
        $user = User::factory()->create();
        
        $invalidFile = UploadedFile::fake()->create('test.txt', 1024, 'text/plain');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/posts', [
                'content' => 'Test post with invalid file',
                'video' => $invalidFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['video']);
    }

    public function test_can_get_video_processing_status()
    {
        $user = User::factory()->create();
        $video = Video::factory()->create([
            'encoding_status' => 'processing'
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/videos/{$video->id}/status");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $video->id,
                'encoding_status' => 'processing',
            ]);
    }
}