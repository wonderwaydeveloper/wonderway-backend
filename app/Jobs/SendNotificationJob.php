<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public int $fromUserId,
        public string $type,
        public int $notifiableId,
        public string $notifiableType
    ) {
    }

    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'from_user_id' => $this->fromUserId,
            'type' => $this->type,
            'notifiable_id' => $this->notifiableId,
            'notifiable_type' => $this->notifiableType,
        ]);
    }
}
