<?php

namespace App\Listeners;

use App\Events\UserFollowed;
use App\Services\NotificationService;

class SendFollowNotification
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function handle(UserFollowed $event): void
    {
        $this->notificationService->notifyFollow($event->follower, $event->followedUser);
    }
}
