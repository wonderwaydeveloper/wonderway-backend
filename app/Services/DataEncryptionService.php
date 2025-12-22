<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DataEncryptionService
{
    /**
     * Encrypt sensitive data before storing
     */
    public function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['phone', 'email', 'two_factor_secret', 'backup_codes'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                $data[$field] = Crypt::encryptString($data[$field]);
            }
        }

        return $data;
    }

    /**
     * Decrypt sensitive data when retrieving
     */
    public function decryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['phone', 'email', 'two_factor_secret', 'backup_codes'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && ! empty($data[$field])) {
                try {
                    $data[$field] = Crypt::decryptString($data[$field]);
                } catch (\Exception $e) {
                    // If decryption fails, field might not be encrypted
                    // Log this for investigation
                    \Log::warning("Failed to decrypt field: {$field}");
                }
            }
        }

        return $data;
    }

    /**
     * Hash passwords with enhanced security
     */
    public function hashPassword(string $password): string
    {
        return Hash::make($password, [
            'rounds' => 12,
        ]);
    }

    /**
     * Verify password hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }

    /**
     * Generate secure token
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Mask sensitive data for logging
     */
    public function maskSensitiveData(string $data, int $visibleChars = 4): string
    {
        $length = strlen($data);

        if ($length <= $visibleChars) {
            return str_repeat('*', $length);
        }

        return substr($data, 0, $visibleChars) . str_repeat('*', $length - $visibleChars);
    }
}
