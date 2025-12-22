<?php

namespace Tests\Feature;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_poll()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/polls', [
            'post_id' => $post->id,
            'question' => 'What is your favorite color?',
            'options' => ['Red', 'Blue', 'Green'],
            'duration_hours' => 24,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('polls', [
            'post_id' => $post->id,
            'question' => 'What is your favorite color?',
        ]);
        $this->assertDatabaseCount('poll_options', 3);
    }

    public function test_user_can_vote_on_poll()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $poll = Poll::factory()->create(['post_id' => $post->id]);
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);

        $response = $this->actingAs($user)->postJson("/api/polls/{$poll->id}/vote/{$option->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'user_id' => $user->id,
            'poll_option_id' => $option->id,
        ]);
    }

    public function test_user_cannot_vote_twice()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $poll = Poll::factory()->create(['post_id' => $post->id]);
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);

        // First vote
        $this->actingAs($user)->postJson("/api/polls/{$poll->id}/vote/{$option->id}");

        // Second vote should fail
        $response = $this->actingAs($user)->postJson("/api/polls/{$poll->id}/vote/{$option->id}");

        $response->assertStatus(400);
        $response->assertJson(['error' => 'You have already voted']);
    }

    public function test_user_cannot_vote_on_expired_poll()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $poll = Poll::factory()->create([
            'post_id' => $post->id,
            'ends_at' => now()->subHour(),
        ]);
        $option = PollOption::factory()->create(['poll_id' => $poll->id]);

        $response = $this->actingAs($user)->postJson("/api/polls/{$poll->id}/vote/{$option->id}");

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Poll has expired']);
    }

    public function test_can_get_poll_results()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $poll = Poll::factory()->create(['post_id' => $post->id, 'total_votes' => 2]);
        $option1 = PollOption::factory()->create(['poll_id' => $poll->id, 'votes_count' => 1]);
        $option2 = PollOption::factory()->create(['poll_id' => $poll->id, 'votes_count' => 1]);

        $response = $this->actingAs($user)->getJson("/api/polls/{$poll->id}/results");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'poll',
            'results',
            'total_votes',
            'is_expired',
            'user_voted',
        ]);
    }

    public function test_poll_validation_requires_minimum_options()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/polls', [
            'post_id' => $post->id,
            'question' => 'Test question?',
            'options' => ['Only one option'],
            'duration_hours' => 24,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('options');
    }

    public function test_poll_validation_limits_maximum_options()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/api/polls', [
            'post_id' => $post->id,
            'question' => 'Test question?',
            'options' => ['Option 1', 'Option 2', 'Option 3', 'Option 4', 'Option 5'],
            'duration_hours' => 24,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('options');
    }
}
