<?php

namespace Database\Factories;

use App\Models\LiveStream;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LiveStreamFactory extends Factory
{
    protected $model = LiveStream::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'stream_key' => Str::random(32),
            'rtmp_url' => 'rtmp://live.wonderway.com/live/' . Str::random(32),
            'hls_url' => 'https://live.wonderway.com/hls/' . Str::random(32) . '.m3u8',
            'status' => $this->faker->randomElement(['scheduled', 'live', 'ended']),
            'viewer_count' => $this->faker->numberBetween(0, 1000),
            'max_viewers' => $this->faker->numberBetween(0, 2000),
            'started_at' => $this->faker->optional()->dateTimeBetween('-2 hours', 'now'),
            'ended_at' => $this->faker->optional()->dateTimeBetween('now', '+1 hour'),
            'is_private' => $this->faker->boolean(20),
            'category' => $this->faker->randomElement(['gaming', 'music', 'talk', 'education', 'entertainment']),
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'live',
            'started_at' => now()->subMinutes(30),
            'ended_at' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subMinutes(30),
        ]);
    }
}
