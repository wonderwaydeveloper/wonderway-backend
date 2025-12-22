<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_posts_relationship(): void
    {
        $user = User::factory()->create();
        Post::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->posts);
        $this->assertInstanceOf(Post::class, $user->posts->first());
    }

    public function test_user_has_followers_relationship(): void
    {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        $user->followers()->attach($follower->id);

        $this->assertCount(1, $user->followers);
        $this->assertTrue($user->followers->contains($follower));
    }

    public function test_user_has_following_relationship(): void
    {
        $user = User::factory()->create();
        $following = User::factory()->create();

        $user->following()->attach($following->id);

        $this->assertCount(1, $user->following);
        $this->assertTrue($user->following->contains($following));
    }

    public function test_is_following_method(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $this->assertFalse($user->isFollowing($targetUser->id));

        $user->following()->attach($targetUser->id);

        $this->assertTrue($user->isFollowing($targetUser->id));
    }

    public function test_is_under_18_method(): void
    {
        $adult = User::factory()->create([
            'date_of_birth' => now()->subYears(20),
        ]);

        $child = User::factory()->create([
            'date_of_birth' => now()->subYears(15),
        ]);

        $this->assertFalse($adult->isUnder18());
        $this->assertTrue($child->isUnder18());
    }

    public function test_password_is_hashed(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }
}
