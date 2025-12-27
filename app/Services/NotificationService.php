<?php

namespace App\Services;

use App\Contracts\Services\NotificationServiceInterface;
use App\Events\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService implements NotificationServiceInterface
{
    private $emailService;
    private $pushService;

    public function __construct(
        EmailService $emailService = null,
        PushNotificationService $pushService = null
    ) {
        $this->emailService = $emailService;
        $this->pushService = $pushService;
    }

    public function send(\App\DTOs\NotificationDTO $dto): \App\Models\Notification
    {
        return $this->createNotification(
            User::find($dto->userId),
            $dto->type,
            $dto->data
        );
    }

    public function sendToUser(User $user, string $type, array $data): Notification
    {
        return $this->createNotification($user, $type, $data);
    }

    public function sendToFollowers(User $user, string $type, array $data): int
    {
        $followers = $user->followers;
        $count = 0;
        
        foreach ($followers as $follower) {
            $this->createNotification($follower, $type, $data);
            $count++;
        }
        
        return $count;
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->update(['read_at' => now()]) > 0;
    }

    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function getUserNotifications(int $userId, int $limit = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->paginate($limit);
    }

    public function deleteNotification(int $notificationId, int $userId): bool
    {
        return Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    public function updatePreferences(int $userId, array $preferences): bool
    {
        return User::where('id', $userId)
            ->update(['notification_preferences' => $preferences]) > 0;
    }

    public function notifyLike($post, $user)
    {
        $notification = $this->sendToUser($post->user, 'like', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($post->user, 'like', $user->name);
        $this->sendEmailNotification($post->user, 'like', $user->name);
    }

    public function notifyComment($post, $user)
    {
        $notification = $this->sendToUser($post->user, 'comment', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($post->user, 'comment', $user->name);
        $this->sendEmailNotification($post->user, 'comment', $user->name);
    }

    public function notifyFollow($follower, $followee)
    {
        $notification = $this->sendToUser($followee, 'follow', [
            'user_id' => $follower->id,
            'user_name' => $follower->name,
        ]);

        $this->sendPushNotification($followee, 'follow', $follower->name);
        $this->sendEmailNotification($followee, 'follow', $follower->name);
    }

    public function notifyMention($post, $mentionedUser, $mentioningUser)
    {
        $notification = $this->sendToUser($mentionedUser, 'mention', [
            'user_id' => $mentioningUser->id,
            'user_name' => $mentioningUser->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($mentionedUser, 'mention', $mentioningUser->name);
        $this->sendEmailNotification($mentionedUser, 'mention', $mentioningUser->name);
    }

    public function notifyRepost($post, $user)
    {
        $notification = $this->sendToUser($post->user, 'repost', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($post->user, 'repost', $user->name);
        $this->sendEmailNotification($post->user, 'repost', $user->name);
    }

    private function createNotification($user, $type, $data)
    {
        try {
            $notification = Notification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $this->getNotificationTitle($type),
                'message' => $this->getNotificationMessage($type),
                'data' => $data,
                'read_at' => null,
            ]);

            // Broadcast real-time notification
            broadcast(new NotificationSent($notification));

            return $notification;
        } catch (\Exception $e) {
            Log::error('Notification creation failed', ['error' => $e->getMessage()]);

            // Return a new notification instance instead of null
            return Notification::make([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $this->getNotificationTitle($type),
                'message' => $this->getNotificationMessage($type),
                'data' => $data,
                'read_at' => null,
            ]);
        }
    }

    private function sendPushNotification($user, $type, $userName)
    {
        if (! $this->pushService) {
            return;
        }

        try {
            // Check user preferences
            if (! $this->shouldSendPushNotification($user, $type)) {
                return;
            }

            $devices = $user->devices()->where('active', true)->get();

            if ($devices->isEmpty()) {
                return;
            }

            $title = $this->getNotificationTitle($type);
            $body = "$userName $this->getNotificationMessage($type)";

            foreach ($devices as $device) {
                $this->pushService->sendToDevice($device->token, $title, $body);
            }
        } catch (\Exception $e) {
            Log::error('Push notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendEmailNotification($user, $type, $userName)
    {
        if (! $this->emailService) {
            return;
        }

        try {
            // Check user preferences
            if (! $this->shouldSendEmailNotification($user, $type)) {
                return;
            }

            $notification = new \stdClass();
            $notification->type = $type;
            $notification->user_name = $userName;
            $notification->message = $this->getNotificationMessage($type);

            $this->emailService->sendNotificationEmail($user, $notification);
        } catch (\Exception $e) {
            Log::error('Email notification failed', ['error' => $e->getMessage()]);
        }
    }

    private function shouldSendPushNotification($user, $type)
    {
        $preferences = $user->notification_preferences;

        if (! $preferences || ! isset($preferences['push'])) {
            return true; // Default to enabled
        }

        $pushPrefs = $preferences['push'];
        $typeMap = [
            'like' => 'likes',
            'comment' => 'comments',
            'follow' => 'follows',
            'mention' => 'mentions',
            'repost' => 'reposts',
            'message' => 'messages',
        ];

        $prefKey = $typeMap[$type] ?? null;

        return $prefKey ? ($pushPrefs[$prefKey] ?? true) : true;
    }

    private function shouldSendEmailNotification($user, $type)
    {
        $preferences = $user->notification_preferences;

        if (! $preferences || ! isset($preferences['email'])) {
            return true; // Default to enabled
        }

        $emailPrefs = $preferences['email'];
        $typeMap = [
            'like' => 'likes',
            'comment' => 'comments',
            'follow' => 'follows',
            'mention' => 'mentions',
            'repost' => 'reposts',
            'message' => 'messages',
        ];

        $prefKey = $typeMap[$type] ?? null;

        return $prefKey ? ($emailPrefs[$prefKey] ?? true) : true;
    }

    private function getNotificationTitle($type)
    {
        $titles = [
            'like' => 'New Like',
            'comment' => 'New Comment',
            'follow' => 'New Follower',
            'mention' => 'New Mention',
            'repost' => 'New Repost',
        ];

        return $titles[$type] ?? 'New Notification';
    }

    private function getNotificationMessage($type)
    {
        $messages = [
            'like' => 'liked your post',
            'comment' => 'commented on your post',
            'follow' => 'followed you',
            'mention' => 'mentioned you',
            'repost' => 'reposted your post',
        ];

        return $messages[$type] ?? 'New notification';
    }
}
