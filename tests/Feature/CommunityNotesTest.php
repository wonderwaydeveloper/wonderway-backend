<?php

namespace Tests\Feature;

use App\Models\CommunityNote;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_community_note()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/community-notes", [
                'content' => 'This information is misleading. Here are the facts...',
                'sources' => ['https://example.com/fact-check'],
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('community_notes', [
            'post_id' => $post->id,
            'author_id' => $user->id,
            'content' => 'This information is misleading. Here are the facts...',
        ]);
    }

    public function test_user_cannot_create_duplicate_note()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        // Create first note
        CommunityNote::factory()->create([
            'post_id' => $post->id,
            'author_id' => $user->id,
        ]);

        // Try to create second note
        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/community-notes", [
                'content' => 'Another note',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_vote_on_community_note()
    {
        $user = User::factory()->create();
        $note = CommunityNote::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/community-notes/{$note->id}/vote", [
                'vote_type' => 'helpful',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('community_note_votes', [
            'community_note_id' => $note->id,
            'user_id' => $user->id,
            'vote_type' => 'helpful',
        ]);
    }

    public function test_note_gets_approved_with_enough_votes()
    {
        $note = CommunityNote::factory()->create(['status' => 'pending']);
        $users = User::factory()->count(4)->create();

        // Add 4 helpful votes
        foreach ($users as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/community-notes/{$note->id}/vote", [
                    'vote_type' => 'helpful',
                ]);
        }

        $note->refresh();
        $this->assertEquals('approved', $note->status);
        $this->assertNotNull($note->approved_at);
    }

    public function test_can_view_approved_notes_for_post()
    {
        $post = Post::factory()->create();
        $approvedNote = CommunityNote::factory()->create([
            'post_id' => $post->id,
            'status' => 'approved',
        ]);
        $pendingNote = CommunityNote::factory()->create([
            'post_id' => $post->id,
            'status' => 'pending',
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/posts/{$post->id}/community-notes");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'notes');
    }

    public function test_validation_requires_minimum_content_length()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/posts/{$post->id}/community-notes", [
                'content' => 'Short',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }
}