<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimpleMentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_post_creation_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Hello world!',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('posts', [
            'content' => 'Hello world!',
            'user_id' => $user->id,
        ]);
    }

    public function test_mention_processing_works()
    {
        $user = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'testuser']);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Hello @testuser!',
        ]);

        $mentionedUsers = $post->processMentions($post->content);

        $this->assertCount(1, $mentionedUsers);
        $this->assertEquals($mentionedUser->id, $mentionedUsers[0]->id);
    }
}
