<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Follow;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_timeline()
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();
        
        Follow::create([
            'follower_id' => $user->id,
            'following_id' => $followedUser->id
        ]);
        
        $post = Post::factory()->create(['user_id' => $followedUser->id]);
        
        $response = $this->actingAs($user)->getJson('/api/timeline');
        
        $response->assertStatus(200)
                ->assertJsonStructure(['data' => [['id', 'content', 'user_id']]]);
    }

    public function test_timeline_pagination_works()
    {
        $user = User::factory()->create();
        $followedUser = User::factory()->create();
        
        Follow::create([
            'follower_id' => $user->id,
            'following_id' => $followedUser->id
        ]);
        
        Post::factory()->count(25)->create(['user_id' => $followedUser->id]);
        
        $response = $this->actingAs($user)->getJson('/api/timeline?page=1');
        
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(20, count($response->json('data')));
    }
}