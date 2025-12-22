<?php

namespace App\Services;

use Google\Client;
use Google\Service\FirebaseCloudMessaging;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private $messaging;

    public function __construct()
    {
        // Skip Firebase initialization in testing environment
        if (app()->environment('testing')) {
            return;
        }

        $credentialsPath = config('services.firebase.credentials');

        // Skip if no credentials configured
        if (empty($credentialsPath) || ! file_exists($credentialsPath)) {
            Log::warning('Firebase credentials not found, push notifications disabled');

            return;
        }

        $client = new Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $this->messaging = new FirebaseCloudMessaging($client);
    }

    public function sendToDevice($deviceToken, $title, $body, $data = [])
    {
        // Mock response in testing environment
        if (app()->environment('testing')) {
            Log::info('Mock push notification sent', ['device' => $deviceToken]);

            return true;
        }

        // Skip if messaging not initialized
        if (! $this->messaging) {
            Log::warning('Firebase messaging not initialized');

            return false;
        }

        try {
            $message = [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
            ];

            $projectId = config('services.firebase.project_id');
            $response = $this->messaging->projects_messages->send(
                "projects/$projectId",
                ['message' => $message]
            );

            Log::info('Push notification sent', ['device' => $deviceToken]);

            return true;
        } catch (\Exception $e) {
            Log::error('Push notification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function sendToMultiple($deviceTokens, $title, $body, $data = [])
    {
        foreach ($deviceTokens as $token) {
            $this->sendToDevice($token, $title, $body, $data);
        }
    }

    public function sendNotification($user, $type, $data)
    {
        $devices = $user->devices()->where('active', true)->get();

        $title = $this->getNotificationTitle($type);
        $body = $this->getNotificationBody($type, $data);

        foreach ($devices as $device) {
            $this->sendToDevice($device->token, $title, $body, $data);
        }
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

    private function getNotificationBody($type, $data)
    {
        $bodies = [
            'like' => "{$data['user_name']} پست شما را پسند کرد",
            'comment' => "{$data['user_name']} روی پست شما نظر داد",
            'follow' => "{$data['user_name']} شما را دنبال کرد",
            'mention' => "{$data['user_name']} شما را منشن کرد",
            'repost' => "{$data['user_name']} پست شما را بازنشر کرد",
        ];

        return $bodies[$type] ?? 'اعلان جدید';
    }
}
