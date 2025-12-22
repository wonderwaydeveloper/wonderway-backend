<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class RichNotificationService
{
    public function sendRichNotification(User $user, array $data): bool
    {
        $payload = $this->buildRichPayload($data);

        // Send to multiple channels
        $results = [
            'push' => $this->sendPushNotification($user, $payload),
            'email' => $this->sendEmailNotification($user, $payload),
            'in_app' => $this->sendInAppNotification($user, $payload),
        ];

        return in_array(true, $results);
    }

    private function buildRichPayload(array $data): array
    {
        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'icon' => $data['icon'] ?? '/icons/notification.png',
            'image' => $data['image'] ?? null,
            'badge' => $data['badge'] ?? null,
            'actions' => $data['actions'] ?? [],
            'data' => [
                'type' => $data['type'],
                'entity_id' => $data['entity_id'] ?? null,
                'url' => $data['url'] ?? null,
                'timestamp' => now()->toISOString(),
            ],
            'android' => [
                'notification' => [
                    'channel_id' => $data['channel'] ?? 'default',
                    'priority' => 'high',
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $data['title'],
                            'body' => $data['body'],
                        ],
                        'badge' => $data['badge'] ?? 1,
                        'sound' => 'default',
                        'category' => $data['category'] ?? 'GENERAL',
                    ],
                ],
            ],
        ];
    }

    private function sendPushNotification(User $user, array $payload): bool
    {
        $tokens = $user->devices()->pluck('token')->toArray();

        if (empty($tokens)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . config('services.fcm.server_key'),
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                    'icon' => $payload['icon'],
                    'image' => $payload['image'],
                ],
                'data' => $payload['data'],
                'android' => $payload['android'],
                'apns' => $payload['apns'],
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Log::error('Rich push notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendEmailNotification(User $user, array $payload): bool
    {
        if (! $user->email_verified_at) {
            return false;
        }

        try {
            $user->notify(new \App\Notifications\RichEmailNotification($payload));

            return true;
        } catch (\Exception $e) {
            \Log::error('Rich email notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendInAppNotification(User $user, array $payload): bool
    {
        try {
            $user->notifications()->create([
                'type' => 'App\\Notifications\\RichInAppNotification',
                'data' => $payload,
                'read_at' => null,
            ]);

            // Broadcast real-time
            broadcast(new \App\Events\NotificationSent($user, $payload));

            return true;
        } catch (\Exception $e) {
            \Log::error('Rich in-app notification failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function sendPostLikeNotification(Post $post, User $liker): bool
    {
        if ($post->user_id === $liker->id) {
            return false; // Don't notify self
        }

        return $this->sendRichNotification($post->user, [
            'title' => 'New Like',
            'body' => "{$liker->name} liked your post",
            'icon' => $liker->avatar,
            'image' => $post->image,
            'type' => 'post_like',
            'entity_id' => $post->id,
            'url' => "/posts/{$post->id}",
            'actions' => [
                ['action' => 'view', 'title' => 'View Post'],
                ['action' => 'reply', 'title' => 'Reply'],
            ],
            'category' => 'SOCIAL',
            'channel' => 'likes',
        ]);
    }

    public function sendFollowNotification(User $follower, User $followed): bool
    {
        return $this->sendRichNotification($followed, [
            'title' => 'New Follower',
            'body' => "{$follower->name} started following you",
            'icon' => $follower->avatar,
            'type' => 'follow',
            'entity_id' => $follower->id,
            'url' => "/users/{$follower->username}",
            'actions' => [
                ['action' => 'view_profile', 'title' => 'View Profile'],
                ['action' => 'follow_back', 'title' => 'Follow Back'],
            ],
            'category' => 'SOCIAL',
            'channel' => 'follows',
        ]);
    }
}
