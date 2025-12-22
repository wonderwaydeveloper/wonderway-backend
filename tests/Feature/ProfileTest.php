<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_user_profile()
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'bio' => 'Test bio',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'username', 'bio', 'avatar',
            ]);
    }

    public function test_can_view_user_posts()
    {
        $user = User::factory()->create();
        Post::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}/posts");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_update_profile()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile', [
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);
    }

    public function test_user_can_update_avatar()
    {
        $user = User::factory()->create([
            'bio' => 'Test bio',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/profile', [
                'name' => $user->name,
                'bio' => $user->bio,
                'avatar' => 'https://example.com/avatar.jpg',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar' => 'https://example.com/avatar.jpg',
        ]);
    }

    public function test_can_search_users()
    {
        User::factory()->create(['name' => 'John Doe', 'username' => 'johndoe']);
        User::factory()->create(['name' => 'Jane Smith', 'username' => 'janesmith']);

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/search/users?q=john');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'username'],
                ],
            ]);
    }

    public function test_user_can_update_privacy_settings()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/privacy', [
            'is_private' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_private' => true,
        ]);
    }

    public function test_guest_cannot_update_profile()
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }
}
