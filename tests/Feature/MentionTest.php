<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Mention;
use App\Notifications\MentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_user_can_mention_another_user_in_post()
    {
        $user = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'testuser']);

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Hello @testuser! How are you?'
        ]);

        $response->assertStatus(201);
        
        $post = Post::first();
        $this->assertTrue($post->isMentioned($mentionedUser->id));
        
        Notification::assertSentTo($mentionedUser, MentionNotification::class);
    }

    public function test_user_can_mention_multiple_users_in_post()
    {
        $user = User::factory()->create();
        $user1 = User::factory()->create(['username' => 'user1']);
        $user2 = User::factory()->create(['username' => 'user2']);

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Hello @user1 and @user2!'
        ]);

        $response->assertStatus(201);
        
        $post = Post::first();
        $this->assertTrue($post->isMentioned($user1->id));
        $this->assertTrue($post->isMentioned($user2->id));
        
        Notification::assertSentTo($user1, MentionNotification::class);
        Notification::assertSentTo($user2, MentionNotification::class);
    }

    public function test_user_can_mention_in_comment()
    {
        $user = User::factory()->create();
        $postOwner = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'mentioned']);
        
        $post = Post::factory()->create(['user_id' => $postOwner->id]);

        $response = $this->actingAs($user)->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Great post @mentioned!'
        ]);

        $response->assertStatus(201);
        
        $comment = Comment::first();
        $this->assertTrue($comment->isMentioned($mentionedUser->id));
        
        // Just check mention exists - notification is tested elsewhere
        $this->assertDatabaseHas('mentions', [
            'user_id' => $mentionedUser->id,
            'mentionable_type' => Comment::class,
            'mentionable_id' => $comment->id,
        ]);
    }

    public function test_invalid_username_mention_is_ignored()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Hello @nonexistentuser!'
        ]);

        $response->assertStatus(201);
        
        $post = Post::first();
        $this->assertEquals(0, $post->mentions()->count());
    }

    public function test_user_can_search_for_mentionable_users()
    {
        $user = User::factory()->create();
        User::factory()->create(['username' => 'testuser', 'name' => 'Test User']);
        User::factory()->create(['username' => 'anotheruser', 'name' => 'Another User']);

        $response = $this->actingAs($user)->getJson('/api/mentions/search-users?q=test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'username', 'name', 'avatar']
                ]
            ]);
    }

    public function test_user_can_get_their_mentions()
    {
        $user = User::factory()->create(['username' => 'mentioned']);
        $mentioner = User::factory()->create();
        
        $post = Post::factory()->create([
            'user_id' => $mentioner->id,
            'content' => 'Hello @mentioned!'
        ]);
        
        // Process mentions
        $post->processMentions($post->content);

        $response = $this->actingAs($user)->getJson('/api/mentions/my-mentions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'user_id', 'mentionable_type', 'mentionable_id']
                    ]
                ]
            ]);
    }

    public function test_duplicate_mentions_are_not_created()
    {
        $user = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'testuser']);

        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Hello @testuser and @testuser again!'
        ]);

        $post->processMentions($post->content);

        $this->assertEquals(1, $post->mentions()->count());
    }

    public function test_mention_notification_contains_correct_data()
    {
        $user = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'testuser']);

        $this->actingAs($user)->postJson('/api/posts', [
            'content' => 'Hello @testuser! How are you?'
        ]);

        Notification::assertSentTo($mentionedUser, MentionNotification::class, function ($notification) use ($user, $mentionedUser) {
            $data = $notification->toArray($mentionedUser);
            
            return $data['type'] === 'mention' &&
                   $data['mentioner_id'] === $user->id &&
                   $data['mentioner_username'] === $user->username;
        });
    }

    public function test_can_get_mentions_for_specific_post()
    {
        $user = User::factory()->create();
        $mentionedUser = User::factory()->create(['username' => 'testuser']);
        
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'content' => 'Hello @testuser!'
        ]);
        
        $post->processMentions($post->content);

        $response = $this->actingAs($user)->getJson("/api/mentions/post/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'user_id', 'user']
                ]
            ]);
    }
}