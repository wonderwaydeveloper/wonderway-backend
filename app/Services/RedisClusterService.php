<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisClusterService
{
    public function getClusterInfo(): array
    {
        try {
            if (! config('database.redis.options.cluster')) {
                return ['status' => 'disabled', 'nodes' => []];
            }

            $redis = Redis::connection();
            $info = $redis->command('CLUSTER', ['INFO']);

            return [
                'status' => 'active',
                'cluster_state' => $this->parseClusterInfo($info),
                'nodes' => $this->getClusterNodes(),
            ];
        } catch (\Exception $e) {
            Log::error('Redis cluster info failed', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    public function getClusterNodes(): array
    {
        try {
            $redis = Redis::connection();
            $nodes = $redis->command('CLUSTER', ['NODES']);

            return $this->parseClusterNodes($nodes);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function checkNodeHealth(): array
    {
        $nodes = config('database.redis.clusters.default', []);
        $health = [];

        foreach ($nodes as $index => $node) {
            try {
                $redis = new \Redis();
                $redis->connect($node['host'], $node['port'], 1);

                if (isset($node['password'])) {
                    $redis->auth($node['password']);
                }

                $ping = $redis->ping();
                $info = $redis->info();

                $health["node_{$index}"] = [
                    'host' => $node['host'],
                    'port' => $node['port'],
                    'status' => $ping === '+PONG' ? 'healthy' : 'unhealthy',
                    'memory_used' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                ];

                $redis->close();
            } catch (\Exception $e) {
                $health["node_{$index}"] = [
                    'host' => $node['host'],
                    'port' => $node['port'],
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $health;
    }

    private function parseClusterInfo(string $info): array
    {
        $lines = explode("\r\n", $info);
        $parsed = [];

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    private function parseClusterNodes(string $nodes): array
    {
        $lines = explode("\n", trim($nodes));
        $parsed = [];

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = explode(' ', $line);
            if (count($parts) >= 8) {
                $parsed[] = [
                    'id' => $parts[0],
                    'address' => $parts[1],
                    'flags' => $parts[2],
                    'master' => $parts[3] !== '-' ? $parts[3] : null,
                    'ping_sent' => $parts[4],
                    'pong_recv' => $parts[5],
                    'config_epoch' => $parts[6],
                    'link_state' => $parts[7],
                ];
            }
        }

        return $parsed;
    }
}
