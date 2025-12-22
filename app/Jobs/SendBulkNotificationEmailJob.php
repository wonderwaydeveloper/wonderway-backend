<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkNotificationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $backoff = [30, 60, 120];

    protected $userIds;
    protected $notificationType;
    protected $notificationData;

    public function __construct(array $userIds, string $notificationType, array $notificationData)
    {
        $this->userIds = $userIds;
        $this->notificationType = $notificationType;
        $this->notificationData = $notificationData;

        // Set queue priority based on notification type
        $this->onQueue($this->getQueueName($notificationType));
    }

    public function handle(EmailService $emailService): void
    {
        try {
            $users = User::whereIn('id', $this->userIds)
                ->where('email_verified_at', '!=', null)
                ->get();

            $successCount = 0;
            $failureCount = 0;

            foreach ($users as $user) {
                try {
                    // Check user notification preferences
                    if (! $this->shouldSendEmail($user)) {
                        continue;
                    }

                    $emailService->sendNotificationEmail($user, (object) [
                        'type' => $this->notificationType,
                        'data' => $this->notificationData,
                        'created_at' => now(),
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $failureCount++;
                    Log::warning('Failed to send email to user', [
                        'user_id' => $user->id,
                        'notification_type' => $this->notificationType,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Bulk email job completed', [
                'notification_type' => $this->notificationType,
                'total_users' => count($this->userIds),
                'success_count' => $successCount,
                'failure_count' => $failureCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk email job failed', [
                'notification_type' => $this->notificationType,
                'user_count' => count($this->userIds),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk email job permanently failed', [
            'notification_type' => $this->notificationType,
            'user_count' => count($this->userIds),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }

    private function shouldSendEmail(User $user): bool
    {
        $preferences = $user->notification_preferences;

        if (! $preferences || ! isset($preferences['email'])) {
            return true; // Default to enabled if no preferences set
        }

        $emailPrefs = $preferences['email'];

        // Map notification types to preference keys
        $typeMap = [
            'like' => 'likes',
            'comment' => 'comments',
            'follow' => 'follows',
            'mention' => 'mentions',
            'repost' => 'reposts',
            'message' => 'messages',
        ];

        $prefKey = $typeMap[$this->notificationType] ?? null;

        return $prefKey ? ($emailPrefs[$prefKey] ?? true) : true;
    }

    private function getQueueName(string $notificationType): string
    {
        // High priority notifications
        $highPriority = ['message', 'mention'];

        if (in_array($notificationType, $highPriority)) {
            return 'high-priority-emails';
        }

        return 'default-emails';
    }
}
