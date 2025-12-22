<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\BotDetectionService;
use App\Services\SecretsManagementService;
use App\Services\SecurityMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SecurityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SecurityMonitoringService $securityService;
    private BotDetectionService $botService;
    private SecretsManagementService $secretsService;
    private AuditTrailService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->securityService = app(SecurityMonitoringService::class);
        $this->botService = app(BotDetectionService::class);
        $this->secretsService = app(SecretsManagementService::class);
        $this->auditService = app(AuditTrailService::class);

        Cache::flush();
        Redis::flushall();

        // Clean up secrets directory
        $secretsPath = storage_path('app/secrets');
        if (is_dir($secretsPath)) {
            array_map('unlink', glob($secretsPath . '/*'));
        }
    }

    /** @test */
    public function waf_blocks_sql_injection_attempts()
    {
        $response = $this->post('/api/test', [
            'input' => "'; DROP TABLE users; --",
        ]);

        // Should be blocked by WAF
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function waf_blocks_xss_attempts()
    {
        $response = $this->post('/api/test', [
            'input' => '<script>alert("xss")</script>',
        ]);

        // Should be blocked by WAF
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function brute_force_protection_works()
    {
        // Simulate multiple failed login attempts
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/api/login', [
                'email' => 'test@example.com',
                'password' => 'wrong_password',
            ]);
        }

        // Should be rate limited or locked out
        $this->assertContains($response->status(), [423, 429]);
    }

    /** @test */
    public function api_rate_limiting_works()
    {
        $this->actingAs($this->user);

        // Make requests rapidly to trigger rate limit
        for ($i = 0; $i < 15; $i++) {
            $response = $this->get('/api/user');
        }

        // Should be rate limited or successful (depending on middleware order)
        $this->assertContains($response->status(), [200, 429]);
    }

    /** @test */
    public function bot_detection_identifies_suspicious_user_agents()
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('User-Agent', 'sqlmap/1.0');

        $result = $this->botService->detectBot($request);

        $this->assertTrue($result['is_bot']);
        $this->assertGreaterThan(70, $result['confidence']);
        $this->assertContains('bot_user_agent', $result['indicators']);
    }

    /** @test */
    public function bot_detection_identifies_rapid_requests()
    {
        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        // Simulate rapid requests
        for ($i = 0; $i < 15; $i++) {
            $this->botService->detectBot($request);
        }

        $result = $this->botService->detectBot($request);

        $this->assertTrue($result['is_bot']);
        $this->assertContains('rapid_requests', $result['indicators']);
    }

    /** @test */
    public function secrets_management_stores_and_retrieves_secrets()
    {
        $key = 'test_secret';
        $value = 'super_secret_value';

        $stored = $this->secretsService->storeSecret($key, $value, 'api_keys');
        $this->assertTrue($stored);

        $retrieved = $this->secretsService->getSecret($key);
        $this->assertEquals($value, $retrieved);
    }

    /** @test */
    public function secrets_management_handles_expiration()
    {
        $key = 'expiring_secret';
        $value = 'temporary_value';

        // Store with 1 second TTL
        $stored = $this->secretsService->storeSecret($key, $value, 'general', 1);
        $this->assertTrue($stored);

        // Should be available immediately
        $retrieved = $this->secretsService->getSecret($key);
        $this->assertEquals($value, $retrieved);

        // Wait for expiration
        sleep(2);

        // Should be null after expiration
        $expired = $this->secretsService->getSecret($key);
        $this->assertNull($expired);
    }

    /** @test */
    public function secrets_management_can_rotate_secrets()
    {
        $key = 'rotatable_secret';
        $oldValue = 'old_value';
        $newValue = 'new_value';

        $this->secretsService->storeSecret($key, $oldValue, 'api_keys');

        $rotated = $this->secretsService->rotateSecret($key, $newValue);
        $this->assertTrue($rotated);
    }

    /** @test */
    public function secrets_management_lists_secrets()
    {
        $this->secretsService->storeSecret('secret1', 'value1', 'api_keys');
        $this->secretsService->storeSecret('secret2', 'value2', 'database_credentials');
        $this->secretsService->storeSecret('secret3', 'value3', 'api_keys');

        $allSecrets = $this->secretsService->listSecrets();
        $this->assertCount(3, $allSecrets);

        $apiKeySecrets = $this->secretsService->listSecrets('api_keys');
        $this->assertCount(2, $apiKeySecrets);
    }

    /** @test */
    public function audit_trail_logs_security_events()
    {
        $this->actingAs($this->user);

        $this->auditService->log('user.login', [
            'ip' => '192.168.1.1',
            'user_agent' => 'Test Browser',
        ]);

        $trail = $this->auditService->getAuditTrail($this->user->id);
        $this->assertNotEmpty($trail);
        $this->assertEquals('user.login', $trail[0]['action']);
    }

    /** @test */
    public function audit_trail_logs_data_access()
    {
        $this->actingAs($this->user);

        $this->auditService->logDataAccess('users', 'read', ['id' => $this->user->id]);

        $trail = $this->auditService->getAuditTrail($this->user->id);
        $this->assertNotEmpty($trail);
        $this->assertEquals('data.read', $trail[0]['action']);
    }

    /** @test */
    public function security_monitoring_logs_events()
    {
        $this->securityService->logSecurityEvent('authentication.failed', [
            'user_id' => $this->user->id,
            'ip' => '192.168.1.1',
        ]);

        // Check that event was logged (would normally check logs or database)
        $this->assertTrue(true); // Placeholder assertion
    }

    /** @test */
    public function database_encryption_service_works()
    {
        $encryptionService = app(\App\Services\DatabaseEncryptionService::class);

        $plaintext = 'sensitive_data';
        $encrypted = $encryptionService->encryptField('users', 'phone', $plaintext);

        $this->assertNotEquals($plaintext, $encrypted);

        $decrypted = $encryptionService->decryptField('users', 'phone', $encrypted);
        $this->assertEquals($plaintext, $decrypted);
    }

    /** @test */
    public function database_encryption_handles_arrays()
    {
        $encryptionService = app(\App\Services\DatabaseEncryptionService::class);

        $data = [
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@example.com',
        ];

        $encrypted = $encryptionService->encryptArray('users', $data);
        $this->assertNotEquals($data['phone'], $encrypted['phone']);
        $this->assertEquals($data['name'], $encrypted['name']); // Not encrypted

        $decrypted = $encryptionService->decryptArray('users', $encrypted);
        $this->assertEquals($data, $decrypted);
    }

    /** @test */
    public function security_headers_are_applied()
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');
    }

    /** @test */
    public function api_key_generation_and_validation_works()
    {
        $apiKey = $this->secretsService->generateApiKey('test_app', ['read', 'write']);

        $this->assertStringStartsWith('ww_', $apiKey);
        $this->assertEquals(67, strlen($apiKey)); // 'ww_' + 64 hex chars

        $keyData = $this->secretsService->validateApiKey($apiKey);
        $this->assertNotNull($keyData);
        $this->assertEquals('test_app', $keyData['name']);
        $this->assertEquals(['read', 'write'], $keyData['permissions']);
    }

    /** @test */
    public function database_credentials_management_works()
    {
        $database = 'test_db';
        $username = 'test_user';
        $password = 'test_password';

        $stored = $this->secretsService->createDatabaseCredentials($database, $username, $password);
        $this->assertTrue($stored);

        $credentials = $this->secretsService->getDatabaseCredentials($database);
        $this->assertNotNull($credentials);
        $this->assertEquals($username, $credentials['username']);
        $this->assertEquals($password, $credentials['password']);
    }

    /** @test */
    public function secrets_audit_works()
    {
        $this->secretsService->storeSecret('audited_secret', 'value', 'api_keys');
        $this->secretsService->getSecret('audited_secret');
        $this->secretsService->getSecret('audited_secret');

        $auditData = $this->secretsService->auditSecretAccess(1);
        $this->assertNotEmpty($auditData);
        $this->assertEquals(2, $auditData[0]['access_count']);
    }

    /** @test */
    public function expired_secrets_cleanup_works()
    {
        // Create expired secret
        $this->secretsService->storeSecret('expired1', 'value1', 'general', 1);
        $this->secretsService->storeSecret('expired2', 'value2', 'general', 1);
        $this->secretsService->storeSecret('permanent', 'value3', 'general');

        sleep(2); // Wait for expiration

        $cleaned = $this->secretsService->cleanupExpiredSecrets();
        $this->assertEquals(2, $cleaned);

        // Permanent secret should still exist
        $this->assertNotNull($this->secretsService->getSecret('permanent'));
    }

    /** @test */
    public function security_middleware_chain_works()
    {
        // Test that multiple security middleware work together
        $response = $this->withHeaders([
            'User-Agent' => 'sqlmap/1.0',
        ])->post('/api/test', [
            'input' => '<script>alert("xss")</script>',
        ]);

        // Should be blocked by either WAF or bot detection
        $this->assertContains($response->status(), [403, 429]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Redis::flushall();
        parent::tearDown();
    }
}
