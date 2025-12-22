<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_requires_existing_email(): void
    {
        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_verify_reset_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Create a password reset token
        \DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make('test-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/password/verify-token', [
            'email' => 'test@example.com',
            'token' => 'test-token',
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    public function test_user_can_reset_password(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Create a password reset token
        \DB::table('password_reset_tokens')->insert([
            'email' => 'test@example.com',
            'token' => Hash::make('test-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'test-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));

        // Verify token was deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_password_reset_requires_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'invalid-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_password_reset_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'test@example.com',
            'token' => 'test-token',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
