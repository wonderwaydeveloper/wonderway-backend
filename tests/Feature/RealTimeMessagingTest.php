<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealTimeMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_broadcasts_when_sent()
    {
        Event::fake();

        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $this->actingAs($sender, 'sanctum')
            ->postJson("/api/messages/users/{$receiver->id}", [
                'content' => 'Hello World',
            ])
            ->assertStatus(201);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_typing_indicator_broadcasts()
    {
        Event::fake();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create conversation first
        $conversation = Conversation::create([
            'user_one_id' => $user1->id,
            'user_two_id' => $user2->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($user1, 'sanctum')
            ->postJson("/api/messages/users/{$user2->id}/typing", [
                'is_typing' => true,
            ])
            ->assertStatus(200);

        Event::assertDispatched(UserTyping::class);
    }

    public function test_message_sent_event_structure()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        $conversation = Conversation::create([
            'user_one_id' => $sender->id,
            'user_two_id' => $receiver->id,
            'last_message_at' => now(),
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Test message',
        ]);

        $event = new MessageSent($message);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channels[0]);

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('id', $broadcastData);
        $this->assertArrayHasKey('content', $broadcastData);
        $this->assertArrayHasKey('sender_id', $broadcastData);
    }
}
