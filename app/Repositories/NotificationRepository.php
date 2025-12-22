<?php

namespace App\Repositories;

use App\Contracts\NotificationRepositoryInterface;
use App\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationRepository implements NotificationRepositoryInterface
{
    public function create(array $data): Notification
    {
        $notification = Notification::create($data);
        $this->clearUserNotificationCache($notification->user_id);

        return $notification;
    }

    public function findById(int $id): ?Notification
    {
        return Notification::find($id);
    }

    public function getUserNotifications(int $userId): LengthAwarePaginator
    {
        return Notification::where('user_id', $userId)
            ->with([
                'notifiable' => function ($morphTo) {
                    $morphTo->morphWith([
                        'App\Models\Post' => ['user:id,name,username,avatar'],
                        'App\Models\User' => ['id', 'name', 'username', 'avatar'],
                        'App\Models\Comment' => ['user:id,name,username,avatar', 'post:id,content'],
                    ]);
                },
            ])
            ->latest()
            ->paginate(20);
    }

    public function getUnreadNotifications(int $userId): Collection
    {
        return Cache::remember("notifications:unread:{$userId}", 60, function () use ($userId) {
            return Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->with([
                    'notifiable' => function ($morphTo) {
                        $morphTo->morphWith([
                            'App\Models\Post' => ['user:id,name,username,avatar'],
                            'App\Models\User' => ['id', 'name', 'username', 'avatar'],
                        ]);
                    },
                ])
                ->latest()
                ->limit(50)
                ->get();
        });
    }

    public function markAsRead(int $notificationId): bool
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $result = $notification->update(['read_at' => now()]);
            $this->clearUserNotificationCache($notification->user_id);

            return $result;
        }

        return false;
    }

    public function markAllAsRead(int $userId): bool
    {
        $result = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $this->clearUserNotificationCache($userId);

        return $result > 0;
    }

    public function getUnreadCount(int $userId): int
    {
        return Cache::remember("notifications:count:{$userId}", 60, function () use ($userId) {
            return Notification::where('user_id', $userId)
                ->whereNull('read_at')
                ->count();
        });
    }

    public function getRecentNotifications(int $userId, int $limit = 10): Collection
    {
        return Notification::where('user_id', $userId)
            ->with([
                'notifiable' => function ($morphTo) {
                    $morphTo->morphWith([
                        'App\Models\Post' => ['user:id,name,username,avatar'],
                        'App\Models\User' => ['id', 'name', 'username', 'avatar'],
                    ]);
                },
            ])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function deleteOldNotifications(int $userId, int $daysOld = 30): int
    {
        $deleted = Notification::where('user_id', $userId)
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();

        if ($deleted > 0) {
            $this->clearUserNotificationCache($userId);
        }

        return $deleted;
    }

    public function getNotificationsByType(int $userId, string $type): Collection
    {
        return Notification::where('user_id', $userId)
            ->where('type', $type)
            ->with('notifiable')
            ->latest()
            ->limit(20)
            ->get();
    }

    private function clearUserNotificationCache(int $userId): void
    {
        Cache::forget("notifications:unread:{$userId}");
        Cache::forget("notifications:count:{$userId}");
    }
}
