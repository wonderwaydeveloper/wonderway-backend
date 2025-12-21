<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MomentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'privacy' => fake()->randomElement(['public', 'private']),
            'is_featured' => fake()->boolean(10), // 10% chance
            'posts_count' => 0,
            'views_count' => fake()->numberBetween(0, 1000),
        ];
    }

    public function public()
    {
        return $this->state(['privacy' => 'public']);
    }

    public function private()
    {
        return $this->state(['privacy' => 'private']);
    }

    public function featured()
    {
        return $this->state(['is_featured' => true]);
    }
}