<?php

namespace Database\Factories;

use App\Models\Stream;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamFactory extends Factory
{
    protected $model = Stream::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'stream_key' => 'stream_' . $this->faker->unique()->regexify('[A-Za-z0-9]{32}'),
            'status' => $this->faker->randomElement(['created', 'live', 'ended']),
            'is_private' => $this->faker->boolean(20),
            'category' => $this->faker->randomElement(['gaming', 'music', 'talk', 'education', 'entertainment', 'sports', 'technology', 'other']),
            'scheduled_at' => null,
            'started_at' => null,
            'ended_at' => null,
            'duration' => 0,
            'peak_viewers' => $this->faker->numberBetween(0, 1000),
            'recording_path' => null,
            'recording_size' => null,
            'settings' => [
                'allow_chat' => true,
                'record_stream' => true,
                'quality_options' => ['480p', '720p', '1080p'],
            ],
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'live',
            'started_at' => now()->subMinutes($this->faker->numberBetween(1, 120)),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'started_at' => now()->subHours($this->faker->numberBetween(1, 24)),
            'ended_at' => now()->subMinutes($this->faker->numberBetween(1, 60)),
            'duration' => $this->faker->numberBetween(300, 7200), // 5 minutes to 2 hours
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }
}
