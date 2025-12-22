<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

class ShardManager
{
    private int $shardsCount;

    public function __construct()
    {
        $this->shardsCount = config('database.shards_count', 4);
    }

    public function getShardForUser(int $userId): string
    {
        $shardId = $userId % $this->shardsCount;

        return "shard_{$shardId}";
    }

    public function getUserShard(int $userId): Connection
    {
        $shardName = $this->getShardForUser($userId);

        return DB::connection($shardName);
    }

    public function getAllShards(): array
    {
        $shards = [];
        for ($i = 0; $i < $this->shardsCount; $i++) {
            $shards[] = "shard_{$i}";
        }

        return $shards;
    }

    public function executeOnAllShards(\Closure $callback): array
    {
        $results = [];
        foreach ($this->getAllShards() as $shard) {
            try {
                $connection = DB::connection($shard);
                $results[$shard] = $callback($connection);
            } catch (\Exception $e) {
                $results[$shard] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    public function migrateUserToShard(int $userId, string $targetShard): bool
    {
        $currentShard = $this->getShardForUser($userId);

        if ($currentShard === $targetShard) {
            return true;
        }

        try {
            DB::transaction(function () use ($userId, $currentShard, $targetShard) {
                $currentConnection = DB::connection($currentShard);
                $targetConnection = DB::connection($targetShard);

                // Move user data
                $userData = $currentConnection->table('users')->where('id', $userId)->first();
                if ($userData) {
                    $targetConnection->table('users')->insert((array) $userData);
                    $currentConnection->table('users')->where('id', $userId)->delete();
                }

                // Move posts
                $posts = $currentConnection->table('posts')->where('user_id', $userId)->get();
                foreach ($posts as $post) {
                    $targetConnection->table('posts')->insert((array) $post);
                }
                $currentConnection->table('posts')->where('user_id', $userId)->delete();

                // Move other user-related data...
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Shard migration failed', [
                'user_id' => $userId,
                'from' => $currentShard,
                'to' => $targetShard,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
