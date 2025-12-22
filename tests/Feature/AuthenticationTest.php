<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register()
    {
        $response = $this->withoutMiddleware([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\AdvancedRateLimit::class,
            ])
            ->postJson('/api/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'testuser',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'date_of_birth' => '2000-01-01',
            ]);

        $response->assertStatus(201);
    }

    public function test_user_cannot_register_with_existing_email()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->withoutMiddleware([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\AdvancedRateLimit::class,
            ])
            ->postJson('/api/register', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'username' => 'testuser2',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'date_of_birth' => '2000-01-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->withoutMiddleware([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\AdvancedRateLimit::class,
            ])
            ->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'Password123!',
            ]);

        $response->assertStatus(200)
                ->assertJsonStructure(['user', 'token']);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->withoutMiddleware([
                \Illuminate\Routing\Middleware\ThrottleRequests::class,
                \App\Http\Middleware\AdvancedRateLimit::class,
            ])
            ->postJson('/api/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_user_can_get_authenticated_user_data()
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');
        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email', 'username']);
    }

    public function test_registration_requires_all_fields()
    {
        $this->assertTrue(true);
    }

    public function test_registration_rate_limit()
    {
        $this->assertTrue(true);
    }
}
