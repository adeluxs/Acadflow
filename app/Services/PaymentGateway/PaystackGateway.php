<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackGateway extends AbstractGateway
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://api.paystack.co';
    }

    public function getCode(): string
    {
        return 'paystack';
    }

    public function getName(): string
    {
        return 'Paystack';
    }

    public function initializePayment(array $data): array
    {
        $response = Http::withToken($this->getSecretKey())
            ->post($this->baseUrl . '/transaction/initialize', [
                'email' => $data['email'],
                'amount' => $data['amount'] * 100, // Convert to kobo/lowest currency unit
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'NGN',
                'callback_url' => $data['callback_url'] ?? null,
                'metadata' => $data['metadata'] ?? [
                    'user_id' => $data['user_id'] ?? null,
                    'order_id' => $data['order_id'] ?? null,
                    'type' => $data['type'] ?? 'subscription'
                ],
                'channels' => $data['channels'] ?? ['card', 'bank', 'ussd', 'qr', 'mobile_money', 'bank_transfer']
            ]);

        if ($response->failed()) {
            Log::error('Paystack initialization failed', [
                'response' => $response->json(),
                'request' => $data
            ]);
            
            return [
                'status' => false,
                'message' => 'Failed to initialize payment: ' . $response->json('message', 'Unknown error'),
            ];
        }

        $responseData = $response->json();

        return [
            'status' => true,
            'authorization_url' => $responseData['data']['authorization_url'],
            'access_code' => $responseData['data']['access_code'],
            'reference' => $responseData['data']['reference'],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withToken($this->getSecretKey())
            ->get($this->baseUrl . '/transaction/verify/' . $reference);

        if ($response->failed()) {
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);
            
            return [
                'status' => false,
                'message' => 'Failed to verify payment',
            ];
        }

        $responseData = $response->json();

        return [
            'status' => true,
            'data' => $responseData['data'],
            'paid' => $responseData['data']['status'] === 'success',
        ];
    }

    public function refund(string $transactionId, float $amount = null): array
    {
        $payload = [
            'transaction' => $transactionId,
            'reason' => 'Refund requested',
        ];

        if ($amount !== null) {
            $payload['amount'] = $amount * 100;
        }

        $response = Http::withToken($this->getSecretKey())
            ->post($this->baseUrl . '/refund', $payload);

        if ($response->failed()) {
            Log::error('Paystack refund failed', [
                'transactionId' => $transactionId,
                'response' => $response->json(),
            ]);
            
            return [
                'status' => false,
                'message' => 'Refund failed: ' . $response->json('message', 'Unknown error'),
            ];
        }

        return [
            'status' => true,
            'data' => $response->json()['data'],
        ];
    }

    public function getStatus(string $reference): string
    {
        $response = Http::withToken($this->getSecretKey())
            ->get($this->baseUrl . '/transaction/verify/' . $reference);

        if ($response->failed()) {
            return 'failed';
        }

        return $response->json('data.status', 'failed');
    }

    public function validateWebhook(array $payload, ?string $signature = null): bool
    {
        if (empty($signature) || empty($this->getSecretKey())) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', json_encode($payload), $this->getSecretKey());
        
        return hash_equals($computedSignature, $signature);
    }

    public function getConfigFields(): array
    {
        return [
            'environment' => [
                'type' => 'select',
                'label' => 'Environment',
                'options' => [
                    'test' => 'Test Mode',
                    'live' => 'Live Mode',
                ],
                'default' => 'test',
                'required' => true,
            ],
            'public_key_test' => [
                'type' => 'text',
                'label' => 'Test Public Key',
                'placeholder' => 'PK_test_XXXXXXXXXXXXXX',
                'required' => false,
            ],
            'secret_key_test' => [
                'type' => 'password',
                'label' => 'Test Secret Key',
                'placeholder' => 'sk_test_XXXXXXXXXXXXXX',
                'required' => false,
            ],
            'public_key_live' => [
                'type' => 'text',
                'label' => 'Live Public Key',
                'placeholder' => 'PK_live_XXXXXXXXXXXXXX',
                'required' => false,
            ],
            'secret_key_live' => [
                'type' => 'password',
                'label' => 'Live Secret Key',
                'placeholder' => 'sk_live_XXXXXXXXXXXXXX',
                'required' => false,
            ],
            'webhook_secret' => [
                'type' => 'password',
                'label' => 'Webhook Secret',
                'hint' => 'Found in Paystack dashboard under Settings → Webhooks',
                'required' => false,
            ],
            'supported_currencies' => [
                'type' => 'multiselect',
                'label' => 'Supported Currencies',
                'options' => array_combine($this->getSupportedCurrencies(), $this->getSupportedCurrencies()),
                'default' => ['NGN', 'USD'],
                'required' => true,
            ],
        ];
    }

    private function getSecretKey(): string
    {
        $isLive = $this->settings['environment'] ?? 'live';
        $keyField = $isLive === 'live' ? 'secret_key_live' : 'secret_key_test';
        
        return $this->credentials[$keyField] ?? '';
    }

    public function getPublicKey(): string
    {
        $isLive = $this->settings['environment'] ?? 'live';
        $keyField = $isLive === 'live' ? 'public_key_live' : 'public_key_test';
        
        return $this->credentials[$keyField] ?? '';
    }
}
