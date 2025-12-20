<?php

namespace App\Services;

use App\Events\NotificationSent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
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

    public function notifyLike($post, $user)
    {
        $notification = $this->createNotification($post->user, 'like', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($post->user, 'like', $user->name);
        $this->sendEmailNotification($post->user, 'like', $user->name);
    }

    public function notifyComment($post, $user)
    {
        $notification = $this->createNotification($post->user, 'comment', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($post->user, 'comment', $user->name);
        $this->sendEmailNotification($post->user, 'comment', $user->name);
    }

    public function notifyFollow($follower, $followee)
    {
        $notification = $this->createNotification($followee, 'follow', [
            'user_id' => $follower->id,
            'user_name' => $follower->name,
        ]);

        $this->sendPushNotification($followee, 'follow', $follower->name);
        $this->sendEmailNotification($followee, 'follow', $follower->name);
    }

    public function notifyMention($post, $mentionedUser, $mentioningUser)
    {
        $notification = $this->createNotification($mentionedUser, 'mention', [
            'user_id' => $mentioningUser->id,
            'user_name' => $mentioningUser->name,
            'post_id' => $post->id,
        ]);

        $this->sendPushNotification($mentionedUser, 'mention', $mentioningUser->name);
        $this->sendEmailNotification($mentionedUser, 'mention', $mentioningUser->name);
    }

    public function notifyRepost($post, $user)
    {
        $notification = $this->createNotification($post->user, 'repost', [
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
            return null;
        }
    }

    private function sendPushNotification($user, $type, $userName)
    {
        if (!$this->pushService) {
            return;
        }
        
        try {
            // Check user preferences
            if (!$this->shouldSendPushNotification($user, $type)) {
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
        if (!$this->emailService) {
            return;
        }
        
        try {
            // Check user preferences
            if (!$this->shouldSendEmailNotification($user, $type)) {
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
        
        if (!$preferences || !isset($preferences['push'])) {
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
        
        if (!$preferences || !isset($preferences['email'])) {
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
            'like' => 'پسند جدید',
            'comment' => 'نظر جدید',
            'follow' => 'دنبال کننده جدید',
            'mention' => 'منشن جدید',
            'repost' => 'بازنشر جدید',
        ];

        return $titles[$type] ?? 'اعلان جدید';
    }

    private function getNotificationMessage($type)
    {
        $messages = [
            'like' => 'پست شما را پسند کرد',
            'comment' => 'روی پست شما نظر داد',
            'follow' => 'شما را دنبال کرد',
            'mention' => 'شما را منشن کرد',
            'repost' => 'پست شما را بازنشر کرد',
        ];

        return $messages[$type] ?? 'اعلان جدید';
    }
}
