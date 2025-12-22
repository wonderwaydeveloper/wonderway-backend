<?php

namespace Database\Factories;

use App\Models\GroupConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupConversationFactory extends Factory
{
    protected $model = GroupConversation::class;

    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'created_by' => User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
