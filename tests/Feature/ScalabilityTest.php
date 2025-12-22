<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DatabaseService;
use App\Services\QueueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_manager_can_dispatch_jobs()
    {
        $user = User::factory()->create();
        $queueManager = app(QueueManager::class);

        $job = new \App\Jobs\ProcessPostJob(\App\Models\Post::factory()->create());
        $result = $queueManager->dispatch($job, QueueManager::HIGH_PRIORITY);

        $this->assertNotNull($result);
    }

    public function test_queue_stats_are_accessible()
    {
        $queueManager = app(QueueManager::class);
        $stats = $queueManager->getQueueStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('high', $stats);
        $this->assertArrayHasKey('default', $stats);
        $this->assertArrayHasKey('low', $stats);
    }

    public function test_database_service_provides_connections()
    {
        $databaseService = app(DatabaseService::class);

        $readConnection = $databaseService->getReadConnection();
        $writeConnection = $databaseService->getWriteConnection();

        $this->assertNotNull($readConnection);
        $this->assertNotNull($writeConnection);
    }

    public function test_monitoring_dashboard_is_accessible()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/monitoring/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'database',
            'cache',
            'queue',
            'system',
        ]);
    }

    public function test_cache_monitoring_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/monitoring/cache');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'cluster_info',
            'node_health',
            'cache_stats',
        ]);
    }

    public function test_queue_monitoring_works()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/monitoring/queue');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stats',
            'failed_jobs',
        ]);
    }
}
