<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class DatabaseEncryptionService
{
    private array $encryptedFields = [
        'users' => ['phone', 'two_factor_secret', 'backup_codes'],
        'user_profiles' => ['address', 'national_id'],
        'payments' => ['card_number', 'account_number'],
        'messages' => ['content'],
        'audit_logs' => ['sensitive_data'],
    ];

    public function encryptField(string $table, string $field, $value): ?string
    {
        if (! $this->shouldEncrypt($table, $field) || empty($value)) {
            return $value;
        }

        try {
            return Crypt::encrypt($value);
        } catch (\Exception $e) {
            Log::error("Encryption failed for {$table}.{$field}", [
                'error' => $e->getMessage(),
                'table' => $table,
                'field' => $field,
            ]);

            throw $e;
        }
    }

    public function decryptField(string $table, string $field, $value): ?string
    {
        if (! $this->shouldEncrypt($table, $field) || empty($value)) {
            return $value;
        }

        try {
            return Crypt::decrypt($value);
        } catch (\Exception $e) {
            Log::error("Decryption failed for {$table}.{$field}", [
                'error' => $e->getMessage(),
                'table' => $table,
                'field' => $field,
            ]);

            return null;
        }
    }

    public function encryptArray(string $table, array $data): array
    {
        foreach ($data as $field => $value) {
            if ($this->shouldEncrypt($table, $field)) {
                $data[$field] = $this->encryptField($table, $field, $value);
            }
        }

        return $data;
    }

    public function decryptArray(string $table, array $data): array
    {
        foreach ($data as $field => $value) {
            if ($this->shouldEncrypt($table, $field)) {
                $data[$field] = $this->decryptField($table, $field, $value);
            }
        }

        return $data;
    }

    private function shouldEncrypt(string $table, string $field): bool
    {
        return isset($this->encryptedFields[$table]) &&
               in_array($field, $this->encryptedFields[$table]);
    }

    public function rotateEncryption(string $table, string $field): void
    {
        // Implementation for key rotation
        Log::info("Starting encryption rotation for {$table}.{$field}");

        // This would typically involve:
        // 1. Generate new encryption key
        // 2. Re-encrypt all data with new key
        // 3. Update key reference
        // 4. Verify integrity
    }
}
