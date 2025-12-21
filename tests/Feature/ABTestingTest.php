<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ABTestingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ABTestingTest extends TestCase
{
    use RefreshDatabase;

    private $abTestingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->abTestingService = app(ABTestingService::class);
    }

    public function test_can_create_ab_test()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/ab-tests', [
            'name' => 'button_color_test',
            'description' => 'Testing button colors',
            'variants' => [
                'A' => ['button_color' => 'blue'],
                'B' => ['button_color' => 'red']
            ],
            'traffic_percentage' => 50
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ab_tests', [
            'name' => 'button_color_test',
            'status' => 'draft'
        ]);
    }

    public function test_user_assignment_to_test()
    {
        $testId = $this->abTestingService->createTest(
            'test_assignment',
            'Test user assignment',
            ['A' => ['feature' => 'old'], 'B' => ['feature' => 'new']],
            100 // 100% traffic
        );

        $this->abTestingService->startTest($testId);

        $user = User::factory()->create();
        $variant = $this->abTestingService->assignUserToTest('test_assignment', $user);

        $this->assertContains($variant, ['A', 'B']);
        $this->assertDatabaseHas('ab_test_participants', [
            'ab_test_id' => $testId,
            'user_id' => $user->id,
            'variant' => $variant
        ]);
    }

    public function test_event_tracking()
    {
        $testId = $this->abTestingService->createTest(
            'conversion_test',
            'Test conversions',
            ['A' => [], 'B' => []],
            100
        );

        $this->abTestingService->startTest($testId);

        $user = User::factory()->create();
        $variant = $this->abTestingService->assignUserToTest('conversion_test', $user);

        $tracked = $this->abTestingService->trackEvent(
            'conversion_test',
            $user,
            'conversion',
            ['value' => 100]
        );

        $this->assertTrue($tracked);
        $this->assertDatabaseHas('ab_test_events', [
            'ab_test_id' => $testId,
            'user_id' => $user->id,
            'variant' => $variant,
            'event_type' => 'conversion'
        ]);
    }

    public function test_assign_endpoint()
    {
        $testId = $this->abTestingService->createTest(
            'api_test',
            'API Test',
            ['A' => [], 'B' => []],
            100
        );

        $this->abTestingService->startTest($testId);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/ab-tests/assign', [
            'test_name' => 'api_test'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['variant', 'in_test']);
        $this->assertTrue($response->json('in_test'));
    }

    public function test_track_endpoint()
    {
        $testId = $this->abTestingService->createTest(
            'track_test',
            'Track Test',
            ['A' => [], 'B' => []],
            100
        );

        $this->abTestingService->startTest($testId);

        $user = User::factory()->create();
        $this->abTestingService->assignUserToTest('track_test', $user);

        $response = $this->actingAs($user)->postJson('/api/ab-tests/track', [
            'test_name' => 'track_test',
            'event_type' => 'click',
            'event_data' => ['button' => 'signup']
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('tracked'));
    }

    public function test_get_test_results()
    {
        $testId = $this->abTestingService->createTest(
            'results_test',
            'Results Test',
            ['A' => [], 'B' => []],
            100
        );

        $this->abTestingService->startTest($testId);

        // Create test data for both variants
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Assign users to both variants
        $variantA = $this->abTestingService->assignUserToTest('results_test', $userA);
        $variantB = $this->abTestingService->assignUserToTest('results_test', $userB);

        // Track events for both variants
        $this->abTestingService->trackEvent('results_test', $userA, 'conversion');
        $this->abTestingService->trackEvent('results_test', $userB, 'view');

        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson("/api/ab-tests/{$testId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'test',
            'participants',
            'results',
            'conversion_rates'
        ]);
    }

    public function test_low_traffic_percentage()
    {
        $testId = $this->abTestingService->createTest(
            'low_traffic_test',
            'Low Traffic Test',
            ['A' => [], 'B' => []],
            0 // 0% traffic
        );

        $this->abTestingService->startTest($testId);

        $user = User::factory()->create();
        $variant = $this->abTestingService->assignUserToTest('low_traffic_test', $user);

        $this->assertNull($variant); // User should not be assigned
    }
}