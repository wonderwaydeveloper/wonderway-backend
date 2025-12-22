<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConnectionManagementService
{
    private const CONNECTION_PREFIX = 'connection:';
    private const USER_CONNECTIONS_PREFIX = 'user_connections:';
    private const CONNECTION_TTL = 300; // 5 minutes

    public function addConnection(string $connectionId, int $userId, array $metadata = []): void
    {
        $connectionData = [
            'user_id' => $userId,
            'connected_at' => now()->timestamp,
            'last_activity' => now()->timestamp,
            'metadata' => $metadata,
        ];

        Redis::setex(
            self::CONNECTION_PREFIX . $connectionId,
            self::CONNECTION_TTL,
            json_encode($connectionData)
        );

        Redis::sadd(self::USER_CONNECTIONS_PREFIX . $userId, $connectionId);
        Redis::expire(self::USER_CONNECTIONS_PREFIX . $userId, self::CONNECTION_TTL);
    }

    public function removeConnection(string $connectionId): void
    {
        $connectionData = $this->getConnection($connectionId);

        if ($connectionData) {
            Redis::del(self::CONNECTION_PREFIX . $connectionId);
            Redis::srem(self::USER_CONNECTIONS_PREFIX . $connectionData['user_id'], $connectionId);
        }
    }

    public function updateActivity(string $connectionId): void
    {
        $connectionData = $this->getConnection($connectionId);

        if ($connectionData) {
            $connectionData['last_activity'] = now()->timestamp;
            Redis::setex(
                self::CONNECTION_PREFIX . $connectionId,
                self::CONNECTION_TTL,
                json_encode($connectionData)
            );
        }
    }

    public function getConnection(string $connectionId): ?array
    {
        $data = Redis::get(self::CONNECTION_PREFIX . $connectionId);

        return $data ? json_decode($data, true) : null;
    }

    public function getUserConnections(int $userId): array
    {
        $connectionIds = Redis::smembers(self::USER_CONNECTIONS_PREFIX . $userId);
        $connections = [];

        foreach ($connectionIds as $connectionId) {
            $connection = $this->getConnection($connectionId);
            if ($connection) {
                $connections[$connectionId] = $connection;
            } else {
                Redis::srem(self::USER_CONNECTIONS_PREFIX . $userId, $connectionId);
            }
        }

        return $connections;
    }

    public function isUserOnline(int $userId): bool
    {
        return count($this->getUserConnections($userId)) > 0;
    }

    public function getOnlineUsersCount(): int
    {
        $pattern = self::USER_CONNECTIONS_PREFIX . '*';
        $keys = Redis::keys($pattern);

        return count(array_filter($keys, function ($key) {
            return Redis::scard($key) > 0;
        }));
    }

    public function cleanupStaleConnections(): int
    {
        $cleaned = 0;
        $pattern = self::CONNECTION_PREFIX . '*';
        $keys = Redis::keys($pattern);

        foreach ($keys as $key) {
            $data = Redis::get($key);
            if ($data) {
                $connection = json_decode($data, true);
                $lastActivity = $connection['last_activity'] ?? 0;

                if (now()->timestamp - $lastActivity > self::CONNECTION_TTL) {
                    $connectionId = str_replace(self::CONNECTION_PREFIX, '', $key);
                    $this->removeConnection($connectionId);
                    $cleaned++;
                }
            }
        }

        Log::info("Cleaned up {$cleaned} stale connections");

        return $cleaned;
    }
}
