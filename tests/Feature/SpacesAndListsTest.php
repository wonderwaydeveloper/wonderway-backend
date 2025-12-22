<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\SpaceParticipant;
use App\Models\User;
use App\Models\UserList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpacesAndListsTest extends TestCase
{
    use RefreshDatabase;

    // Spaces Tests
    public function test_user_can_create_space(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/spaces', [
                'title' => 'Test Space',
                'description' => 'A test audio room',
                'privacy' => 'public',
                'max_participants' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'title', 'description', 'status', 'host',
            ]);

        $this->assertDatabaseHas('spaces', [
            'host_id' => $user->id,
            'title' => 'Test Space',
            'status' => 'live',
        ]);
    }

    public function test_user_can_join_public_space(): void
    {
        $host = User::factory()->create();
        $user = User::factory()->create();

        $space = Space::factory()->live()->public()->create([
            'host_id' => $host->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/join");

        $response->assertStatus(200);

        $this->assertDatabaseHas('space_participants', [
            'space_id' => $space->id,
            'user_id' => $user->id,
            'status' => 'joined',
        ]);
    }

    public function test_user_can_leave_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create(['status' => 'live']);

        SpaceParticipant::create([
            'space_id' => $space->id,
            'user_id' => $user->id,
            'status' => 'joined',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/leave");

        $response->assertStatus(200);

        $this->assertDatabaseHas('space_participants', [
            'space_id' => $space->id,
            'user_id' => $user->id,
            'status' => 'left',
        ]);
    }

    public function test_host_can_end_space(): void
    {
        $host = User::factory()->create();
        $space = Space::factory()->create([
            'host_id' => $host->id,
            'status' => 'live',
        ]);

        $response = $this->actingAs($host, 'sanctum')
            ->postJson("/api/spaces/{$space->id}/end");

        $response->assertStatus(200);

        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'status' => 'ended',
        ]);
    }

    // Lists Tests
    public function test_user_can_create_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lists', [
                'name' => 'My Test List',
                'description' => 'A list for testing',
                'privacy' => 'public',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'name', 'description', 'privacy',
            ]);

        $this->assertDatabaseHas('lists', [
            'user_id' => $user->id,
            'name' => 'My Test List',
        ]);
    }

    public function test_user_can_add_member_to_list(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = UserList::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/lists/{$list->id}/members", [
                'user_id' => $member->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('list_members', [
            'list_id' => $list->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_user_can_subscribe_to_public_list(): void
    {
        $owner = User::factory()->create();
        $subscriber = User::factory()->create();
        $list = UserList::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'public',
        ]);

        $response = $this->actingAs($subscriber, 'sanctum')
            ->postJson("/api/lists/{$list->id}/subscribe");

        $response->assertStatus(200);

        $this->assertDatabaseHas('list_subscribers', [
            'list_id' => $list->id,
            'user_id' => $subscriber->id,
        ]);
    }

    public function test_user_cannot_subscribe_to_private_list(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $list = UserList::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'private',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/lists/{$list->id}/subscribe");

        $response->assertStatus(403);
    }

    public function test_user_can_view_list_posts(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $list = UserList::factory()->create([
            'user_id' => $owner->id,
            'privacy' => 'public',
        ]);

        $list->members()->attach($member->id);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/lists/{$list->id}/posts");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_user_can_discover_public_lists(): void
    {
        $user = User::factory()->create();
        UserList::factory()->create(['privacy' => 'public']);
        UserList::factory()->create(['privacy' => 'private']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/lists/discover');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [],
            ]);
    }

    public function test_guest_cannot_access_spaces(): void
    {
        $response = $this->getJson('/api/spaces');
        $response->assertStatus(401);

        $response = $this->postJson('/api/spaces', []);
        $response->assertStatus(401);
    }

    public function test_guest_cannot_access_lists(): void
    {
        $response = $this->getJson('/api/lists');
        $response->assertStatus(401);

        $response = $this->postJson('/api/lists', []);
        $response->assertStatus(401);
    }
}
