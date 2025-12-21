<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AutoScalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoScalingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_scaling_status()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auto-scaling/status');

        $response->assertOk()
            ->assertJsonStructure([
                'metrics',
                'recommendations',
                'timestamp'
            ]);
    }

    public function test_can_get_current_metrics()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auto-scaling/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'cpu_usage',
                'memory_usage',
                'active_connections',
                'queue_size',
                'response_time',
                'error_rate',
                'throughput'
            ]);
    }

    public function test_can_force_scaling_action()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auto-scaling/force-scale', [
                'action' => 'scale_up',
                'reason' => 'Manual testing',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Scaling action executed successfully']);
    }

    public function test_can_get_load_prediction()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/auto-scaling/predict?hours=24');

        $response->assertOk()
            ->assertJsonStructure([
                'predicted_cpu',
                'predicted_memory',
                'predicted_throughput',
                'confidence'
            ]);
    }

    public function test_auto_scaling_service_analyzes_metrics_correctly()
    {
        $service = new AutoScalingService();
        
        $highCpuMetrics = [
            'cpu_usage' => 90,
            'memory_usage' => 50,
            'queue_size' => 100,
            'response_time' => 500,
        ];

        $recommendations = $service->analyzeMetrics($highCpuMetrics);
        
        $this->assertNotEmpty($recommendations);
        $this->assertEquals('scale_up', $recommendations[0]['type']);
        $this->assertEquals('High CPU usage', $recommendations[0]['reason']);
    }

    public function test_scaling_validation_requires_valid_action()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/auto-scaling/force-scale', [
                'action' => 'invalid_action',
                'reason' => 'Test',
            ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_access_auto_scaling()
    {
        $response = $this->getJson('/api/auto-scaling/status');
        $response->assertStatus(401);
    }
}