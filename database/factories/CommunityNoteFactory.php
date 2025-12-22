<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_id' => User::factory(),
            'content' => $this->faker->paragraph(3),
            'sources' => [
                $this->faker->url(),
                $this->faker->url(),
            ],
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'helpful_votes' => $this->faker->numberBetween(0, 10),
            'not_helpful_votes' => $this->faker->numberBetween(0, 5),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
            'helpful_votes' => $this->faker->numberBetween(3, 10),
        ]);
    }
}