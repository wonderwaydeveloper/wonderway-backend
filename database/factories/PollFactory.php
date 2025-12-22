<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PollFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'question' => $this->faker->sentence() . '?',
            'ends_at' => $this->faker->dateTimeBetween('now', '+7 days'),
            'total_votes' => 0,
        ];
    }
}
