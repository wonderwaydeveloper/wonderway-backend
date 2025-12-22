<?php

namespace Tests\Feature;

use App\Models\GroupConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_group_chat()
    {
        $user = User::factory()->create();
        $members = User::factory()->count(3)->create();

        $response = $this->actingAs($user)->postJson('/api/groups', [
            'name' => 'Test Group',
            'member_ids' => $members->pluck('id')->toArray(),
        ]);

        $response->assertStatus(201);
    }

    public function test_user_can_send_group_message()
    {
        $user = User::factory()->create();
        $group = GroupConversation::factory()->create();
        $group->members()->attach($user->id);

        $response = $this->actingAs($user)->postJson("/api/groups/{$group->id}/messages", [
            'content' => 'Hello group!',
        ]);

        $response->assertStatus(201);
    }
}
