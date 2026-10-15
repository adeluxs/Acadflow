<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected string $baseUrl;
    protected string $senderId;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.sms.base_url', 'https://api.sms-provider.com/v1');
        $this->senderId = config('services.sms.sender_id', 'AcadFlow');
        $this->apiKey = config('services.sms.api_key', '');
    }

    public function send(string $phoneNumber, string $message): array
    {
        if (empty($this->apiKey)) {
            return [
                'status' => false,
                'message' => 'SMS API key not configured',
            ];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->post($this->baseUrl.'/messages', [
                    'to' => $this->formatPhoneNumber($phoneNumber),
                    'from' => $this->senderId,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'status' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('SMS sending failed', [
                'phone' => $phoneNumber,
                'response' => $response->body(),
            ]);

            return [
                'status' => false,
                'message' => 'The SMS could not be sent right now. Please try again later.',
                'code' => 'SMS_DELIVERY_FAILED',
                'retryable' => $response->serverError() || $response->status() === 429,
            ];
        } catch (\Throwable $e) {
            Log::error('SMS sending exception', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'The SMS service is temporarily unavailable. Please try again later.',
                'code' => 'SMS_SERVICE_UNAVAILABLE',
                'retryable' => true,
            ];
        }
    }

    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '234'.substr($phoneNumber, 1);
        }

        if (! str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+'.$phoneNumber;
        }

        return $phoneNumber;
    }
}
