<?php

namespace Database\Factories;

use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

class PollOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'text' => $this->faker->word(),
            'votes_count' => 0,
        ];
    }
}
