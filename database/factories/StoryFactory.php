<?php

namespace Database\Factories;

use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoryFactory extends Factory
{
    protected $model = Story::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'content' => $this->faker->sentence(),
            'media_url' => $this->faker->imageUrl(),
            'media_type' => 'image',
            'expires_at' => now()->addHours(24),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}