<?php

namespace Database\Factories\Monetization\Models;

use App\Monetization\Models\Advertisement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvertisementFactory extends Factory
{
    protected $model = Advertisement::class;

    public function definition(): array
    {
        return [
            'advertiser_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->sentence(10),
            'media_url' => $this->faker->optional()->imageUrl(),
            'target_audience' => ['all'],
            'budget' => $this->faker->randomFloat(2, 50, 1000),
            'cost_per_click' => 0.10,
            'cost_per_impression' => 0.01,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 'active',
            'impressions_count' => $this->faker->numberBetween(0, 1000),
            'clicks_count' => $this->faker->numberBetween(0, 100),
            'conversions_count' => $this->faker->numberBetween(0, 10),
            'total_spent' => $this->faker->randomFloat(2, 0, 100),
            'targeting_criteria' => [],
        ];
    }
}