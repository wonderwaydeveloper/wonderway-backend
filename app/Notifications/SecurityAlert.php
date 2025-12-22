<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    private array $alertData;

    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getSubject();
        $message = new MailMessage();

        $message->subject($subject)
                ->greeting('Security Alert')
                ->line($this->getDescription())
                ->line('Alert Details:');

        foreach ($this->getAlertDetails() as $key => $value) {
            $message->line("• {$key}: {$value}");
        }

        $message->line('Please investigate this security event immediately.')
                ->action('View Security Dashboard', url('/admin/security'))
                ->line('This is an automated security alert from WonderWay.');

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'security_alert',
            'subject' => $this->getSubject(),
            'description' => $this->getDescription(),
            'details' => $this->getAlertDetails(),
            'severity' => $this->getSeverity(),
            'timestamp' => now()->toISOString(),
        ];
    }

    private function getSubject(): string
    {
        $type = $this->alertData['type'] ?? 'security_event';

        return match($type) {
            'security_threshold_exceeded' => 'Security Threshold Exceeded',
            'brute_force_attack' => 'Brute Force Attack Detected',
            'suspicious_activity' => 'Suspicious Activity Detected',
            'data_breach' => 'Potential Data Breach',
            'system_compromise' => 'System Compromise Detected',
            default => 'Security Alert'
        };
    }

    private function getDescription(): string
    {
        $type = $this->alertData['type'] ?? 'security_event';

        return match($type) {
            'security_threshold_exceeded' =>
                "Security threshold exceeded for {$this->alertData['event_type']}. " .
                "Count: {$this->alertData['count']}, Threshold: {$this->alertData['threshold']}",
            'brute_force_attack' =>
                'Multiple failed login attempts detected from the same source.',
            'suspicious_activity' =>
                'Unusual user behavior patterns have been detected.',
            'data_breach' =>
                'Unauthorized access to sensitive data has been detected.',
            'system_compromise' =>
                'Potential system compromise indicators have been found.',
            default => 'A security event requires your attention.'
        };
    }

    private function getAlertDetails(): array
    {
        $details = [];

        if (isset($this->alertData['source_ip'])) {
            $details['Source IP'] = $this->alertData['source_ip'];
        }

        if (isset($this->alertData['user_id'])) {
            $details['User ID'] = $this->alertData['user_id'];
        }

        if (isset($this->alertData['timestamp'])) {
            $details['Timestamp'] = $this->alertData['timestamp'];
        }

        if (isset($this->alertData['count'])) {
            $details['Event Count'] = $this->alertData['count'];
        }

        if (isset($this->alertData['event_type'])) {
            $details['Event Type'] = $this->alertData['event_type'];
        }

        return $details;
    }

    private function getSeverity(): string
    {
        return $this->alertData['severity'] ?? 'medium';
    }
}
