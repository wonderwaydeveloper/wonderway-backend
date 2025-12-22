<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_edit_their_post_within_time_limit()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Original content',
            'created_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user);

        $response = $this->putJson("/api/posts/{$post->id}", [
            'content' => 'Updated content',
            'edit_reason' => 'Fixed typo',
        ]);

        $response->assertStatus(200);

        $post->refresh();
        $this->assertEquals('Updated content', $post->content);
        $this->assertTrue($post->is_edited);
        $this->assertNotNull($post->last_edited_at);

        $this->assertDatabaseHas('post_edits', [
            'post_id' => $post->id,
            'original_content' => 'Original content',
            'new_content' => 'Updated content',
            'edit_reason' => 'Fixed typo',
        ]);
    }

    public function test_user_cannot_edit_post_after_time_limit()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Original content',
            'created_at' => now()->subMinutes(35),
        ]);

        $this->actingAs($user);

        $response = $this->putJson("/api/posts/{$post->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'Post cannot be edited after 30 minutes',
        ]);
    }

    public function test_user_can_view_edit_history()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Original content',
        ]);

        // Edit the post
        $post->editPost('Updated content', 'Fixed typo');

        $this->actingAs($user);

        $response = $this->getJson("/api/posts/{$post->id}/edit-history");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'current_content',
            'edit_history' => [
                '*' => [
                    'original_content',
                    'new_content',
                    'edit_reason',
                    'edited_at',
                ],
            ],
        ]);
    }

    public function test_user_cannot_edit_others_post()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user1->id]);

        $this->actingAs($user2);

        $response = $this->putJson("/api/posts/{$post->id}", [
            'content' => 'Hacked content',
        ]);

        $response->assertStatus(403);
    }
}
