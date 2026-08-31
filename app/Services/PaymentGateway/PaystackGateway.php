<?php

namespace App\Services\PaymentGateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
        $response = $this->client()
            ->post($this->baseUrl . '/transaction/initialize', [
                'email' => $data['email'],
                'amount' => isset($data['amount_minor']) ? (int) $data['amount_minor'] : \App\Support\Money::toMinor((string) $data['amount']),
                'reference' => $data['reference'],
                'currency' => $data['currency'] ?? 'NGN',
                'callback_url' => $data['callback_url'] ?? null,
                'metadata' => $data['metadata'] ?? [
                    'user_id' => $data['user_id'] ?? null,
                    'order_id' => $data['order_id'] ?? null,
                    'type' => $data['type'] ?? 'payment'
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
                'message' => $response->status() === 429
                    ? 'The payment service is temporarily busy. Please try again shortly.'
                    : 'We could not start the payment right now. Please try again.',
                'code' => $response->status() === 429 ? 'PAYMENT_GATEWAY_RATE_LIMITED' : 'PAYMENT_GATEWAY_REJECTED',
                'retryable' => $response->status() === 429 || $response->serverError(),
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
        $response = $this->client()
            ->get($this->baseUrl . '/transaction/verify/' . $reference);

        if ($response->failed()) {
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'response' => $response->json(),
            ]);
            
            return [
                'status' => false,
                'message' => $response->status() === 429
                    ? 'The payment service is temporarily busy. Please try again shortly.'
                    : 'We could not verify the payment right now. Please try again.',
                'code' => $response->status() === 429 ? 'PAYMENT_GATEWAY_RATE_LIMITED' : 'PAYMENT_VERIFICATION_FAILED',
                'retryable' => $response->status() === 429 || $response->serverError(),
            ];
        }

        $responseData = $response->json();

        return [
            'status' => true,
            'data' => $responseData['data'],
            'paid' => $responseData['data']['status'] === 'success',
        ];
    }

    public function refund(string $transactionId, ?int $amountMinor = null): array
    {
        $payload = [
            'transaction' => $transactionId,
            'reason' => 'Refund requested',
        ];

        if ($amountMinor !== null) {
            $payload['amount'] = $amountMinor;
        }

        try {
            // Refund POSTs are deliberately never auto-retried. A transport timeout can
            // occur after Paystack accepted the request, so replaying automatically could
            // issue a duplicate refund.
            $response = $this->client()->post($this->baseUrl . '/refund', $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Paystack refund outcome is unknown after a transport failure', [
                'transactionId' => $transactionId,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'The refund provider did not return a conclusive response. Do not retry this refund until it has been reconciled with Paystack.',
                'code' => 'REFUND_OUTCOME_UNKNOWN',
                'retryable' => false,
                'outcome_unknown' => true,
            ];
        }

        if ($response->failed()) {
            Log::error('Paystack refund failed', [
                'transactionId' => $transactionId,
                'http_status' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'status' => false,
                'message' => 'Paystack rejected the refund request. No local refund was posted.',
                'code' => 'REFUND_REQUEST_FAILED',
                'retryable' => false,
                'outcome_unknown' => false,
                'data' => $response->json(),
            ];
        }

        $data = (array) ($response->json('data') ?? []);

        return [
            'status' => true,
            'data' => $data,
            'outcome_unknown' => false,
        ];
    }

    public function getStatus(string $reference): string
    {
        $response = $this->client()
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


    private function client(): PendingRequest
    {
        // Bound external calls so a provider/network problem cannot leave the UI
        // hanging indefinitely. Transactional POSTs are intentionally not given
        // automatic retries here because their server-side outcome may be unknown.
        return Http::withToken($this->getSecretKey())
            ->acceptJson()
            ->connectTimeout((int) config('services.paystack.connect_timeout', 8))
            ->timeout((int) config('services.paystack.timeout', 20));
    }

    public function getSecretKey(): string
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
