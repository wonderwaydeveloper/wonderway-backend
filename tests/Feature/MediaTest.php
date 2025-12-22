<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_image()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('test.jpg', 1000, 1000)->size(1024);

        $response = $this->actingAs($user)->postJson('/api/media/upload/image', [
            'image' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_user_can_upload_video()
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.mp4', 5000, 'video/mp4');

        $response = $this->actingAs($user)->postJson('/api/media/upload/video', [
            'video' => $file,
        ]);

        $response->assertStatus(200);
    }

    public function test_upload_validates_file_size()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('large.jpg')->size(20000);

        $response = $this->actingAs($user)->postJson('/api/media/upload/image', [
            'image' => $file,
        ]);

        $response->assertStatus(422);
    }
}
