<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class SmsService
{
    private $client;
    private $fromNumber;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.account_sid'),
            config('services.twilio.auth_token')
        );
        $this->fromNumber = config('services.twilio.phone_number');
    }

    public function sendOtp($phoneNumber, $otp)
    {
        try {
            $message = $this->client->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => "کد تایید WonderWay: $otp",
                ]
            );

            Log::info('SMS sent', ['phone' => $phoneNumber, 'sid' => $message->sid]);

            return true;
        } catch (\Exception $e) {
            Log::error('SMS failed', ['phone' => $phoneNumber, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function sendVerificationCode($phoneNumber, $code)
    {
        return $this->sendOtp($phoneNumber, $code);
    }

    public function sendNotification($phoneNumber, $message)
    {
        try {
            $this->client->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $message,
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('SMS notification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
