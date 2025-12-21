<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpaceFactory extends Factory
{
    protected $model = Space::class;

    public function definition(): array
    {
        return [
            'host_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement(['scheduled', 'live', 'ended']),
            'privacy' => $this->faker->randomElement(['public', 'followers', 'invited']),
            'max_participants' => $this->faker->numberBetween(5, 50),
            'current_participants' => $this->faker->numberBetween(0, 10),
            'scheduled_at' => $this->faker->optional()->dateTimeBetween('now', '+1 week'),
            'settings' => [
                'recording_enabled' => $this->faker->boolean(),
                'chat_enabled' => $this->faker->boolean(80)
            ]
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'live',
            'started_at' => now()->subMinutes($this->faker->numberBetween(1, 120))
        ]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'public'
        ]);
    }
}