<?php

namespace App\Services;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class QueueManager
{
    public const HIGH_PRIORITY = 'high';
    public const DEFAULT_PRIORITY = 'default';
    public const LOW_PRIORITY = 'low';

    public function dispatch($job, string $priority = self::DEFAULT_PRIORITY, int $delay = 0)
    {
        $queue = $this->getQueueName($priority);

        if ($delay > 0) {
            return Queue::later(now()->addSeconds($delay), $job, '', $queue);
        }

        return Queue::push($job, '', $queue);
    }

    public function getQueueStats(): array
    {
        $redis = Redis::connection();

        return [
            'high' => $this->getQueueSize('high'),
            'default' => $this->getQueueSize('default'),
            'low' => $this->getQueueSize('low'),
            'failed' => $this->getFailedJobsCount(),
            'processed_today' => $this->getProcessedToday(),
        ];
    }

    public function getQueueSize(string $priority): int
    {
        try {
            $redis = Redis::connection();
            $queueName = config('queue.connections.redis.queue') . ':' . $priority;

            return $redis->llen($queueName);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getFailedJobsCount(): int
    {
        try {
            return \DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function getProcessedToday(): int
    {
        try {
            $redis = Redis::connection();
            $key = 'queue:processed:' . now()->format('Y-m-d');

            return (int) $redis->get($key);
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function incrementProcessedCount(): void
    {
        try {
            $redis = Redis::connection();
            $key = 'queue:processed:' . now()->format('Y-m-d');
            $redis->incr($key);
            $redis->expire($key, 86400 * 7); // Keep for 7 days
        } catch (\Exception $e) {
            // Ignore errors
        }
    }

    private function getQueueName(string $priority): string
    {
        return match($priority) {
            self::HIGH_PRIORITY => 'high',
            self::LOW_PRIORITY => 'low',
            default => 'default',
        };
    }

    public function retryFailedJobs(int $limit = 10): int
    {
        $failedJobs = \DB::table('failed_jobs')
            ->orderBy('failed_at')
            ->limit($limit)
            ->get();

        $retried = 0;
        foreach ($failedJobs as $job) {
            try {
                $payload = json_decode($job->payload, true);
                Queue::push($payload['data']['command'], '', $this->getQueueName(self::DEFAULT_PRIORITY));

                \DB::table('failed_jobs')->where('id', $job->id)->delete();
                $retried++;
            } catch (\Exception $e) {
                \Log::error('Failed to retry job', [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $retried;
    }
}
