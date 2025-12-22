<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_message(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/users/{$recipient->id}", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'content', 'sender_id']);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $user->id,
            'content' => 'Hello!',
        ]);
    }

    public function test_user_cannot_send_message_to_self(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/users/{$user->id}", [
                'content' => 'Hello!',
            ]);

        $response->assertStatus(400);
    }

    public function test_message_requires_content_or_media(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/users/{$recipient->id}", []);

        $response->assertStatus(400);
    }

    public function test_user_can_view_conversations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $otherUser->id,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/messages/conversations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_one_id', 'user_two_id'],
                ],
            ]);
    }

    public function test_user_can_view_messages_with_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $otherUser->id,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => 'Test message',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/messages/users/{$otherUser->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'content', 'sender_id'],
                ],
            ]);
    }

    public function test_user_can_mark_message_as_read(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $sender->id,
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Test message',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_user_cannot_mark_own_message_as_read(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $otherUser->id,
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => 'Test message',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/messages/{$message->id}/read");

        $response->assertStatus(400);
    }

    public function test_user_can_get_unread_messages_count(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $user->id,
            'user_two_id' => $sender->id,
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Test message',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/messages/unread-count');

        $response->assertStatus(200)
            ->assertJson(['count' => 1]);
    }

    public function test_guest_cannot_send_message(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/messages/users/{$user->id}", [
            'content' => 'Hello!',
        ]);

        $response->assertStatus(401);
    }

    public function test_guest_cannot_view_conversations(): void
    {
        $response = $this->getJson('/api/messages/conversations');

        $response->assertStatus(401);
    }
}
