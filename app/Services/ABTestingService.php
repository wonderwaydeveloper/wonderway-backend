<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ABTestingService
{
    public function assignUserToTest($testName, User $user)
    {
        $test = $this->getActiveTest($testName);
        
        if (!$test) {
            return null;
        }

        // Check if user already assigned
        $existing = DB::table('ab_test_participants')
            ->where('ab_test_id', $test['id'])
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return $existing->variant;
        }

        // Check traffic percentage
        if (rand(1, 100) > $test['traffic_percentage']) {
            return null; // User not in test
        }

        // Assign variant (50/50 split)
        $variant = (crc32($user->id . $test['name']) % 2) ? 'A' : 'B';

        DB::table('ab_test_participants')->insert([
            'ab_test_id' => $test['id'],
            'user_id' => $user->id,
            'variant' => $variant,
            'assigned_at' => now(),
        ]);

        return $variant;
    }

    public function trackEvent($testName, User $user, $eventType, $eventData = null)
    {
        $test = $this->getActiveTest($testName);
        
        if (!$test) {
            return false;
        }

        $participant = DB::table('ab_test_participants')
            ->where('ab_test_id', $test['id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return false;
        }

        DB::table('ab_test_events')->insert([
            'ab_test_id' => $test['id'],
            'user_id' => $user->id,
            'variant' => $participant->variant,
            'event_type' => $eventType,
            'event_data' => $eventData ? json_encode($eventData) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    public function getTestResults($testId)
    {
        $test = DB::table('ab_tests')->find($testId);
        
        if (!$test) {
            return null;
        }

        $results = DB::table('ab_test_events')
            ->select([
                'variant',
                'event_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            ])
            ->where('ab_test_id', $testId)
            ->groupBy(['variant', 'event_type'])
            ->get()
            ->groupBy('variant');

        $participants = DB::table('ab_test_participants')
            ->select(['variant', DB::raw('COUNT(*) as total')])
            ->where('ab_test_id', $testId)
            ->groupBy('variant')
            ->pluck('total', 'variant');

        return [
            'test' => $test,
            'participants' => $participants,
            'results' => $results,
            'conversion_rates' => $this->calculateConversionRates($results, $participants)
        ];
    }

    private function getActiveTest($testName)
    {
        return Cache::remember("ab_test_{$testName}", 300, function () use ($testName) {
            $test = DB::table('ab_tests')
                ->where('name', $testName)
                ->where('status', 'active')
                ->where('starts_at', '<=', now())
                ->where(function($q) {
                    $q->whereNull('ends_at')
                      ->orWhere('ends_at', '>', now());
                })
                ->first();
                
            return $test ? (array) $test : null;
        });
    }

    private function calculateConversionRates($results, $participants)
    {
        $rates = [];
        
        foreach (['A', 'B'] as $variant) {
            $totalParticipants = $participants->get($variant, 0);
            $conversions = $results->get($variant, collect())->where('event_type', 'conversion')->first();
            $conversionCount = $conversions ? $conversions->unique_users : 0;
            
            $rates[$variant] = $totalParticipants > 0 
                ? round(($conversionCount / $totalParticipants) * 100, 2)
                : 0;
        }
        
        return $rates;
    }

    public function createTest($name, $description, $variants, $trafficPercentage = 50)
    {
        return DB::table('ab_tests')->insertGetId([
            'name' => $name,
            'description' => $description,
            'variants' => json_encode($variants),
            'traffic_percentage' => $trafficPercentage,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function startTest($testId)
    {
        DB::table('ab_tests')
            ->where('id', $testId)
            ->update([
                'status' => 'active',
                'starts_at' => now(),
                'updated_at' => now(),
            ]);

        Cache::forget("ab_test_*");
    }

    public function stopTest($testId)
    {
        DB::table('ab_tests')
            ->where('id', $testId)
            ->update([
                'status' => 'completed',
                'ends_at' => now(),
                'updated_at' => now(),
            ]);

        Cache::forget("ab_test_*");
    }
}