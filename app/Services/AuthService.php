<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthService
{
    public function __construct(
        private EmailService $emailService
    ) {
    }

    /**
     * Register a new user
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'date_of_birth' => $data['date_of_birth'],
        ]);

        $user->assignRole('user');

        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'کاربر با موفقیت ثبت شد',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Login user with credentials
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['اطلاعات ورود صحیح نیست'],
            ]);
        }

        // Handle 2FA if enabled
        if ($user->two_factor_enabled) {
            return $this->handle2FA($user, $credentials['two_factor_code'] ?? null);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'ورود موفقیتآمیز بود',
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout user by deleting current token
     */
    public function logout(User $user): array
    {
        $user->currentAccessToken()->delete();

        return ['message' => 'خروج موفقیتآمیز بود'];
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
                'message' => 'کد تأیید دو مرحلهای مورد نیاز است',
                'status' => 403,
            ];
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($secret, $twoFactorCode)) {
            throw ValidationException::withMessages([
                'two_factor_code' => ['کد تأیید دو مرحلهای نامعتبر است'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'message' => 'ورود موفقیتآمیز بود',
            'user' => $user,
            'token' => $token,
        ];
    }
}
