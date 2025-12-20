<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'plan' => $this->faker->randomElement(['basic', 'premium']),
            'status' => $this->faker->randomElement(['active', 'cancelled', 'expired']),
            'amount' => $this->faker->randomFloat(2, 0, 99.99),
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}