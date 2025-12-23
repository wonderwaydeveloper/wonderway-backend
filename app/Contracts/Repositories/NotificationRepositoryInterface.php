<?php

namespace App\Contracts\Repositories;

use App\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function create(array $data): Notification;

    public function findById(int $id): ?Notification;

    public function getUserNotifications(int $userId): LengthAwarePaginator;

    public function getUnreadNotifications(int $userId): Collection;

    public function markAsRead(int $notificationId): bool;

    public function markAllAsRead(int $userId): bool;

    public function getUnreadCount(int $userId): int;

    public function getRecentNotifications(int $userId, int $limit = 10): Collection;

    public function deleteOldNotifications(int $userId, int $daysOld = 30): int;

    public function getNotificationsByType(int $userId, string $type): Collection;
}