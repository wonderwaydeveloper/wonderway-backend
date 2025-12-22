<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQRCodeUrl(string $companyName, string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($companyName, $email, $secret);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        try {
            return $this->google2fa->verifyKey($secret, $code);
        } catch (\Exception $e) {
            // Invalid secret or code format
            return false;
        }
    }

    public function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }

        return $codes;
    }
}
