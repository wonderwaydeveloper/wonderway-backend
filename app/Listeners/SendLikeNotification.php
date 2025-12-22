<?php

namespace App\Listeners;

use App\Events\PostLiked;
use App\Services\NotificationService;

class SendLikeNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function handle(PostLiked $event): void
    {
        if ($event->post->user_id === $event->user->id) {
            return;
        }

        $this->notificationService->notifyLike($event->post, $event->user);
    }
}
