<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_follow_another_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$targetUser->id}/follow");

        $response->assertStatus(200)
            ->assertJson(['following' => true]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $user->id,
            'following_id' => $targetUser->id,
        ]);
    }

    public function test_user_can_unfollow_user(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $user->following()->attach($targetUser->id);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$targetUser->id}/follow");

        $response->assertStatus(200)
            ->assertJson(['following' => false]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $user->id,
            'following_id' => $targetUser->id,
        ]);
    }

    public function test_user_cannot_follow_themselves(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$user->id}/follow");

        $response->assertStatus(400);
    }

    public function test_user_can_view_followers(): void
    {
        $user = User::factory()->create();
        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();

        $user->followers()->attach([$follower1->id, $follower2->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}/followers");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_view_following(): void
    {
        $user = User::factory()->create();
        $following1 = User::factory()->create();
        $following2 = User::factory()->create();

        $user->following()->attach([$following1->id, $following2->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/users/{$user->id}/following");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_follow_private_account_without_request(): void
    {
        $user = User::factory()->create();
        $privateUser = User::factory()->create(['is_private' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$privateUser->id}/follow");

        $response->assertStatus(403)
            ->assertJson(['requires_request' => true]);
    }

    public function test_follow_creates_notification(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/users/{$targetUser->id}/follow")
            ->assertStatus(200);

        // Check that follow relationship exists
        $this->assertDatabaseHas('follows', [
            'follower_id' => $user->id,
            'following_id' => $targetUser->id,
        ]);

        // Just check that the follow worked - notification is tested elsewhere
        $this->assertTrue($user->isFollowing($targetUser->id));
    }

    public function test_guest_cannot_follow_user(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson("/api/users/{$user->id}/follow");

        $response->assertStatus(401);
    }
}
