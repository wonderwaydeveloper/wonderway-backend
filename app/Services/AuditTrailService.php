<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuditTrailService
{
    private array $auditableActions = [
        'user.login',
        'user.logout',
        'user.register',
        'user.password_change',
        'user.profile_update',
        'user.delete',
        'post.create',
        'post.update',
        'post.delete',
        'admin.user_ban',
        'admin.content_moderate',
        'security.suspicious_activity',
        'data.export',
        'data.delete',
        'data.read',
        'data.write',
    ];

    public function log(string $action, array $data = [], ?Request $request = null): void
    {
        if (! in_array($action, $this->auditableActions)) {
            return;
        }

        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'ip_address' => $request?->ip() ?? request()?->ip(),
                'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
                'data' => $this->sanitizeData($data),
                'timestamp' => now(),
                'session_id' => session()->getId(),
                'risk_level' => $this->calculateRiskLevel($action, $data),
            ]);
        } catch (\Exception $e) {
            Log::error('Audit logging failed', [
                'action' => $action,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->log("security.{$event}", array_merge($context, [
            'severity' => $this->getSecuritySeverity($event),
            'requires_investigation' => $this->requiresInvestigation($event),
        ]));

        if ($this->requiresInvestigation($event)) {
            $this->alertSecurityTeam($event, $context);
        }
    }

    public function logDataAccess(string $table, string $operation, array $identifiers = []): void
    {
        $this->log("data.{$operation}", [
            'table' => $table,
            'identifiers' => $identifiers,
            'sensitive' => $this->isSensitiveTable($table),
        ]);
    }

    public function getAuditTrail(int $userId, ?string $action = null, int $days = 30): array
    {
        $query = AuditLog::where('user_id', $userId)
            ->where('timestamp', '>=', now()->subDays($days));

        if ($action) {
            $query->where('action', $action);
        }

        return $query->orderBy('timestamp', 'desc')->get()->toArray();
    }

    public function exportAuditData(array $filters = []): string
    {
        $this->log('data.export', ['filters' => $filters]);

        $query = AuditLog::query();

        foreach ($filters as $field => $value) {
            $query->where($field, $value);
        }

        $data = $query->get();

        // Generate CSV or JSON export
        return $this->generateExport($data);
    }

    private function sanitizeData(array $data): array
    {
        $sensitive = ['password', 'token', 'secret', 'key', 'card_number'];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->containsSensitiveField($key, $sensitive)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    private function containsSensitiveField(string $field, array $sensitive): bool
    {
        foreach ($sensitive as $pattern) {
            if (stripos($field, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function calculateRiskLevel(string $action, array $data): string
    {
        $highRiskActions = ['user.delete', 'admin.user_ban', 'data.export'];
        $mediumRiskActions = ['user.password_change', 'post.delete'];

        if (in_array($action, $highRiskActions)) {
            return 'high';
        }

        if (in_array($action, $mediumRiskActions)) {
            return 'medium';
        }

        return 'low';
    }

    private function getSecuritySeverity(string $event): string
    {
        $critical = ['brute_force', 'sql_injection', 'xss_attempt'];
        $high = ['suspicious_login', 'rate_limit_exceeded'];

        if (in_array($event, $critical)) {
            return 'critical';
        }

        if (in_array($event, $high)) {
            return 'high';
        }

        return 'medium';
    }

    private function requiresInvestigation(string $event): bool
    {
        return in_array($event, ['brute_force', 'sql_injection', 'xss_attempt', 'data_breach']);
    }

    private function isSensitiveTable(string $table): bool
    {
        return in_array($table, ['users', 'payments', 'user_profiles', 'audit_logs']);
    }

    private function alertSecurityTeam(string $event, array $context): void
    {
        // Implementation for security team alerts
        Log::critical("Security event requires investigation: {$event}", $context);
    }

    private function generateExport(array $data): string
    {
        // Implementation for data export
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}
