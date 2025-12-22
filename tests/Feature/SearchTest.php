<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_posts()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Laravel testing guide',
            'is_draft' => false,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/posts?q=Laravel');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'content'],
                ],
            ]);
    }

    public function test_search_users()
    {
        $user = User::factory()->create();
        User::factory()->create([
            'name' => 'John Developer',
            'username' => 'johndev',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/users?q=john');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'username'],
                ],
            ]);
    }

    public function test_search_all()
    {
        $user = User::factory()->create();

        Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Laravel framework',
        ]);

        User::factory()->create([
            'name' => 'Laravel Expert',
            'username' => 'laravelexpert',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/search/all?q=laravel');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'posts' => ['data'],
                'users' => ['data'],
            ]);
    }
}
