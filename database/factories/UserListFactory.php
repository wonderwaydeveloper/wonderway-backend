<?php

namespace Database\Factories;

use App\Models\UserList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserListFactory extends Factory
{
    protected $model = UserList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->paragraph(),
            'privacy' => $this->faker->randomElement(['public', 'private']),
            'members_count' => $this->faker->numberBetween(0, 100),
            'subscribers_count' => $this->faker->numberBetween(0, 500)
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'privacy' => 'public'
        ]);
    }
}