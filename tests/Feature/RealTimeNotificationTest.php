<?php

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Events\NotificationSent;
use App\Events\PostLiked;
use App\Events\UserFollowed;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealTimeNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_broadcasts_on_like()
    {
        Event::fake([PostLiked::class]);

        $postOwner = User::factory()->create();
        $liker = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postOwner->id]);

        $this->actingAs($liker, 'sanctum')
            ->postJson("/api/posts/{$post->id}/like")
            ->assertStatus(200);

        Event::assertDispatched(PostLiked::class);
    }

    public function test_notification_broadcasts_on_comment()
    {
        Event::fake([CommentCreated::class]);

        $postOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $postOwner->id]);

        $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/posts/{$post->id}/comments", [
                'content' => 'Nice post!',
            ])
            ->assertStatus(201);

        Event::assertDispatched(CommentCreated::class);
    }

    public function test_notification_broadcasts_on_follow()
    {
        Event::fake([UserFollowed::class]);

        $follower = User::factory()->create();
        $followee = User::factory()->create();

        $this->actingAs($follower, 'sanctum')
            ->postJson("/api/users/{$followee->id}/follow")
            ->assertStatus(200);

        Event::assertDispatched(UserFollowed::class);
    }

    public function test_notification_sent_event_structure()
    {
        $user = User::factory()->create();

        $notification = \App\Models\Notification::create([
            'user_id' => $user->id,
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $user->id,
            'type' => 'like',
            'title' => 'پسند جدید',
            'message' => 'پست شما را پسند کرد',
            'data' => json_encode(['user_id' => 1]),
        ]);

        $event = new NotificationSent($notification);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(\Illuminate\Broadcasting\PrivateChannel::class, $channels[0]);

        $broadcastData = $event->broadcastWith();
        $this->assertArrayHasKey('id', $broadcastData);
        $this->assertArrayHasKey('type', $broadcastData);
        $this->assertArrayHasKey('title', $broadcastData);
        $this->assertArrayHasKey('message', $broadcastData);
    }
}
