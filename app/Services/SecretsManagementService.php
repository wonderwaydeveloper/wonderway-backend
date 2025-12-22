<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SecretsManagementService
{
    private string $vaultPath;
    private array $secretTypes = [
        'api_keys',
        'database_credentials',
        'encryption_keys',
        'oauth_secrets',
        'webhook_secrets',
        'third_party_tokens',
    ];

    public function __construct()
    {
        $this->vaultPath = storage_path('app/secrets');
        $this->ensureVaultDirectory();
    }

    public function storeSecret(string $key, string $value, string $type = 'general', ?int $ttl = null): bool
    {
        try {
            $secretData = [
                'value' => $this->encryptSecret($value),
                'type' => $type,
                'created_at' => now()->toISOString(),
                'expires_at' => $ttl ? now()->addSeconds($ttl)->toISOString() : null,
                'access_count' => 0,
                'last_accessed' => null,
            ];

            $filePath = $this->getSecretPath($key);
            file_put_contents($filePath, json_encode($secretData));
            chmod($filePath, 0600); // Read/write for owner only

            $this->logSecretOperation('store', $key, $type);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store secret', [
                'key' => $key,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getSecret(string $key): ?string
    {
        try {
            $filePath = $this->getSecretPath($key);

            if (! file_exists($filePath)) {
                return null;
            }

            $secretData = json_decode(file_get_contents($filePath), true);

            // Check expiration
            if ($secretData['expires_at'] && now()->gt($secretData['expires_at'])) {
                $this->deleteSecret($key);

                return null;
            }

            // Update access tracking
            $secretData['access_count']++;
            $secretData['last_accessed'] = now()->toISOString();
            file_put_contents($filePath, json_encode($secretData));

            $this->logSecretOperation('access', $key, $secretData['type']);

            return $this->decryptSecret($secretData['value']);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve secret', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function deleteSecret(string $key): bool
    {
        try {
            $filePath = $this->getSecretPath($key);

            if (file_exists($filePath)) {
                $secretData = json_decode(file_get_contents($filePath), true);
                unlink($filePath);

                $this->logSecretOperation('delete', $key, $secretData['type'] ?? 'unknown');

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to delete secret', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function rotateSecret(string $key, string $newValue): bool
    {
        try {
            $oldSecret = $this->getSecretInfo($key);
            if (! $oldSecret) {
                return false;
            }

            // Store new secret with rotation metadata
            $rotationData = [
                'rotated_from' => $key,
                'rotation_date' => now()->toISOString(),
                'rotation_reason' => 'manual_rotation',
            ];

            $newKey = $key . '_' . now()->format('YmdHis');
            $this->storeSecret($newKey, $newValue, $oldSecret['type']);

            // Update original key to point to new version
            $this->updateSecretMetadata($key, ['current_version' => $newKey]);

            $this->logSecretOperation('rotate', $key, $oldSecret['type']);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to rotate secret', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listSecrets(string $type = null): array
    {
        try {
            $secrets = [];
            $files = glob($this->vaultPath . '/*.json');

            foreach ($files as $file) {
                $key = basename($file, '.json');
                $secretData = json_decode(file_get_contents($file), true);

                if ($type && $secretData['type'] !== $type) {
                    continue;
                }

                $secrets[] = [
                    'key' => $key,
                    'type' => $secretData['type'],
                    'created_at' => $secretData['created_at'],
                    'expires_at' => $secretData['expires_at'],
                    'access_count' => $secretData['access_count'],
                    'last_accessed' => $secretData['last_accessed'],
                ];
            }

            return $secrets;

        } catch (\Exception $e) {
            Log::error('Failed to list secrets', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function generateApiKey(string $name, array $permissions = []): string
    {
        $apiKey = 'ww_' . bin2hex(random_bytes(32));

        $keyData = [
            'name' => $name,
            'permissions' => $permissions,
            'created_at' => now()->toISOString(),
            'last_used' => null,
            'usage_count' => 0,
        ];

        $this->storeSecret("api_key_{$name}", json_encode($keyData), 'api_keys');

        return $apiKey;
    }

    public function validateApiKey(string $apiKey): ?array
    {
        if (! str_starts_with($apiKey, 'ww_')) {
            return null;
        }

        $keyName = $this->findApiKeyName($apiKey);
        if (! $keyName) {
            return null;
        }

        $keyDataJson = $this->getSecret("api_key_{$keyName}");
        if (! $keyDataJson) {
            return null;
        }

        $keyData = json_decode($keyDataJson, true);

        // Update usage tracking
        $keyData['last_used'] = now()->toISOString();
        $keyData['usage_count']++;
        $this->storeSecret("api_key_{$keyName}", json_encode($keyData), 'api_keys');

        return $keyData;
    }

    public function createDatabaseCredentials(string $database, string $username, string $password): bool
    {
        $credentials = [
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'created_at' => now()->toISOString(),
        ];

        return $this->storeSecret("db_{$database}", json_encode($credentials), 'database_credentials');
    }

    public function getDatabaseCredentials(string $database): ?array
    {
        $credentialsJson = $this->getSecret("db_{$database}");

        return $credentialsJson ? json_decode($credentialsJson, true) : null;
    }

    public function auditSecretAccess(int $days = 30): array
    {
        $auditData = [];
        $secrets = $this->listSecrets();

        foreach ($secrets as $secret) {
            if ($secret['last_accessed'] &&
                now()->subDays($days)->lt($secret['last_accessed'])) {

                $auditData[] = [
                    'key' => $secret['key'],
                    'type' => $secret['type'],
                    'access_count' => $secret['access_count'],
                    'last_accessed' => $secret['last_accessed'],
                ];
            }
        }

        return $auditData;
    }

    public function cleanupExpiredSecrets(): int
    {
        $cleaned = 0;
        $secrets = $this->listSecrets();

        foreach ($secrets as $secret) {
            if ($secret['expires_at'] && now()->gt($secret['expires_at'])) {
                if ($this->deleteSecret($secret['key'])) {
                    $cleaned++;
                }
            }
        }

        Log::info("Cleaned up {$cleaned} expired secrets");

        return $cleaned;
    }

    private function encryptSecret(string $value): string
    {
        return Crypt::encrypt($value);
    }

    private function decryptSecret(string $encryptedValue): string
    {
        return Crypt::decrypt($encryptedValue);
    }

    private function getSecretPath(string $key): string
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);

        return $this->vaultPath . '/' . $safeKey . '.json';
    }

    private function ensureVaultDirectory(): void
    {
        if (! is_dir($this->vaultPath)) {
            mkdir($this->vaultPath, 0700, true);
        }
    }

    private function logSecretOperation(string $operation, string $key, string $type): void
    {
        Log::channel('security')->info("Secret {$operation}", [
            'key' => $key,
            'type' => $type,
            'user_id' => auth()?->id(),
            'ip' => request()?->ip(),
        ]);
    }

    private function getSecretInfo(string $key): ?array
    {
        try {
            $filePath = $this->getSecretPath($key);

            if (! file_exists($filePath)) {
                return null;
            }

            return json_decode(file_get_contents($filePath), true);

        } catch (\Exception $e) {
            return null;
        }
    }

    private function updateSecretMetadata(string $key, array $metadata): bool
    {
        try {
            $secretData = $this->getSecretInfo($key);
            if (! $secretData) {
                return false;
            }

            $secretData = array_merge($secretData, $metadata);

            $filePath = $this->getSecretPath($key);
            file_put_contents($filePath, json_encode($secretData));

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function findApiKeyName(string $apiKey): ?string
    {
        // Extract name from API key or use a mapping
        // For testing, we'll use a simple approach
        $secrets = $this->listSecrets('api_keys');

        foreach ($secrets as $secret) {
            if (str_contains($secret['key'], 'api_key_')) {
                $keyDataJson = $this->getSecret($secret['key']);
                if ($keyDataJson) {
                    $keyData = json_decode($keyDataJson, true);

                    // In a real implementation, you'd store the actual API key
                    // For now, return the name from the key
                    return str_replace('api_key_', '', $secret['key']);
                }
            }
        }

        return null;
    }
}
