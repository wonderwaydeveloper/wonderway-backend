<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'original_path' => 'videos/original/' . $this->faker->uuid . '.mp4',
            'processed_paths' => [
                '480p' => 'videos/processed/' . $this->faker->uuid . '_480p.mp4',
                '720p' => 'videos/processed/' . $this->faker->uuid . '_720p.mp4',
                '1080p' => 'videos/processed/' . $this->faker->uuid . '_1080p.mp4',
            ],
            'thumbnail_path' => 'thumbnails/' . $this->faker->uuid . '.jpg',
            'duration' => $this->faker->numberBetween(10, 300),
            'resolution' => '1920x1080',
            'file_size' => $this->faker->numberBetween(1000000, 50000000),
            'encoding_status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'metadata' => [
                'duration' => $this->faker->numberBetween(10, 300),
                'width' => 1920,
                'height' => 1080,
                'codec' => 'h264',
                'bitrate' => '2000000'
            ],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'encoding_status' => 'pending',
            'processed_paths' => null,
            'thumbnail_path' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'encoding_status' => 'completed',
        ]);
    }
}