<?php

namespace App\Services;

use App\Contracts\Services\AuthServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Exceptions\ValidationException;
use App\DTOs\LoginDTO;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private EmailService $emailService
    ) {
    }

    /**
     * Register a new user
     */
    public function register(\App\DTOs\UserRegistrationDTO $dto): array
    {
        $user = User::create([
            'name' => $dto->name,
            'username' => $dto->username,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'date_of_birth' => $dto->dateOfBirth,
        ]);

        $user->assignRole('user');

        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Login user with credentials
     */
    public function login(LoginDTO $loginDTO): array
    {
        $user = User::where('email', $loginDTO->email)->first();
        
        // Use hash_equals to prevent timing attacks
        $validCredentials = $user && Hash::check($loginDTO->password, $user->password);
        
        // Always perform hash check even if user doesn't exist to prevent timing attacks
        if (!$user) {
            Hash::check('dummy-password', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
        }

        if (!$validCredentials) {
            throw new ValidationException([
                'email' => ['Invalid login credentials'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout user by deleting current token
     */
    public function logout(User $user): bool
    {
        $user->currentAccessToken()->delete();
        return true;
    }

    public function refreshToken(string $refreshToken): array
    {
        // Implementation needed
        return [];
    }

    public function forgotPassword(string $email): bool
    {
        // Implementation needed
        return true;
    }

    public function resetPassword(string $token, string $password): bool
    {
        // Implementation needed
        return true;
    }

    public function verifyEmail(string $token): bool
    {
        // Implementation needed
        return true;
    }

    public function resendVerification(User $user): bool
    {
        // Implementation needed
        return true;
    }

    public function enable2FA(User $user): array
    {
        // Implementation needed
        return [];
    }

    public function verify2FA(User $user, string $code): bool
    {
        // Implementation needed
        return true;
    }

    public function disable2FA(User $user): bool
    {
        // Implementation needed
        return true;
    }

    /**
     * Get current user data
     */
    public function getCurrentUser(User $user): User
    {
        return $user;
    }

    /**
     * Handle 2FA verification
     */
    private function handle2FA(User $user, ?string $twoFactorCode): array
    {
        if (! $twoFactorCode) {
            return [
                'requires_2fa' => true,
                'message' => 'Two-factor authentication code required',
                'status' => 403,
            ];
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($secret, $twoFactorCode)) {
            throw new ValidationException([
                'two_factor_code' => ['Invalid two-factor authentication code'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ];
    }
}
