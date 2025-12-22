<?php

namespace App\Listeners;

use App\Events\CommentCreated;
use App\Services\NotificationService;

class SendCommentNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    public function handle(CommentCreated $event): void
    {
        if ($event->comment->post->user_id === $event->user->id) {
            return;
        }

        $this->notificationService->notifyComment($event->comment->post, $event->user);
    }
}
