<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ABTestingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ABTestController extends Controller
{
    private $abTestingService;

    public function __construct(ABTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    public function index()
    {
        $tests = DB::table('ab_tests')
            ->select(['id', 'name', 'description', 'status', 'traffic_percentage', 'starts_at', 'ends_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($tests);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:ab_tests,name',
            'description' => 'nullable|string|max:500',
            'variants' => 'required|array',
            'variants.A' => 'required|array',
            'variants.B' => 'required|array',
            'traffic_percentage' => 'integer|min:1|max:100',
        ]);

        $testId = $this->abTestingService->createTest(
            $request->name,
            $request->description,
            $request->variants,
            $request->input('traffic_percentage', 50)
        );

        return response()->json([
            'message' => 'A/B test created successfully',
            'test_id' => $testId,
        ], 201);
    }

    public function show($id)
    {
        $results = $this->abTestingService->getTestResults($id);

        if (! $results) {
            return response()->json(['message' => 'Test not found'], 404);
        }

        return response()->json($results);
    }

    public function start(Request $request, $id)
    {
        $this->abTestingService->startTest($id);

        return response()->json(['message' => 'Test started successfully']);
    }

    public function stop(Request $request, $id)
    {
        $this->abTestingService->stopTest($id);

        return response()->json(['message' => 'Test stopped successfully']);
    }

    public function assign(Request $request)
    {
        $request->validate([
            'test_name' => 'required|string',
        ]);

        $variant = $this->abTestingService->assignUserToTest(
            $request->test_name,
            $request->user()
        );

        return response()->json([
            'variant' => $variant,
            'in_test' => $variant !== null,
        ]);
    }

    public function track(Request $request)
    {
        $request->validate([
            'test_name' => 'required|string',
            'event_type' => 'required|string',
            'event_data' => 'nullable|array',
        ]);

        $tracked = $this->abTestingService->trackEvent(
            $request->test_name,
            $request->user(),
            $request->event_type,
            $request->event_data
        );

        return response()->json([
            'tracked' => $tracked,
            'message' => $tracked ? 'Event tracked' : 'User not in test',
        ]);
    }
}
