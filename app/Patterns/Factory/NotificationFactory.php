<?php

namespace App\Patterns\Factory;

use App\Services\EmailService;
use App\Services\PushNotificationService;
use App\Services\SmsService;
use InvalidArgumentException;

class NotificationFactory
{
    private EmailService $emailService;
    private PushNotificationService $pushService;
    private SmsService $smsService;

    public function __construct(
        EmailService $emailService,
        PushNotificationService $pushService,
        SmsService $smsService
    ) {
        $this->emailService = $emailService;
        $this->pushService = $pushService;
        $this->smsService = $smsService;
    }

    public function create(string $type): NotificationServiceInterface
    {
        return match ($type) {
            'email' => new EmailNotificationService($this->emailService),
            'push' => new PushNotificationServiceAdapter($this->pushService),
            'sms' => new SmsNotificationService($this->smsService),
            default => throw new InvalidArgumentException("Unknown notification type: {$type}")
        };
    }

    public function createMultiple(array $types): array
    {
        return array_map(fn($type) => $this->create($type), $types);
    }
}

interface NotificationServiceInterface
{
    public function send(string $recipient, string $message, array $data = []): bool;
}

class EmailNotificationService implements NotificationServiceInterface
{
    public function __construct(private EmailService $emailService) {}

    public function send(string $recipient, string $message, array $data = []): bool
    {
        return $this->emailService->sendEmail($recipient, $data['subject'] ?? 'Notification', $message);
    }
}

class PushNotificationServiceAdapter implements NotificationServiceInterface
{
    public function __construct(private PushNotificationService $pushService) {}

    public function send(string $recipient, string $message, array $data = []): bool
    {
        return $this->pushService->sendToUser($recipient, $data['title'] ?? 'Notification', $message, $data);
    }
}

class SmsNotificationService implements NotificationServiceInterface
{
    public function __construct(private SmsService $smsService) {}

    public function send(string $recipient, string $message, array $data = []): bool
    {
        return $this->smsService->sendSms($recipient, $message);
    }
}