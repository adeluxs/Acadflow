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

    public function getSupportedCurrencies(): array
    {
        return ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF'];
    }

    public function initializePayment(array $data): array
    {
        if (! $this->isConfigured()) return $this->failure('PAYMENT_GATEWAY_NOT_CONFIGURED', false);
        $currency = strtoupper((string) ($data['currency'] ?? 'NGN'));
        $amountMinor = isset($data['amount_minor']) ? (int) $data['amount_minor'] : \App\Support\Money::toMinor((string) $data['amount']);
        if ($amountMinor <= 0 || ($currency === 'XOF' && $amountMinor % 100 !== 0)
            || ! in_array($currency, $this->getSupportedCurrencies(), true)
            || ! in_array($currency, $this->settings['supported_currencies'] ?? $this->getSupportedCurrencies(), true)) {
            return $this->failure('PAYMENT_INVALID_AMOUNT_OR_CURRENCY', false);
        }
        try {
            $response = $this->client()
            ->post($this->baseUrl . '/transaction/initialize', [
                'email' => $data['email'],
                'amount' => isset($data['amount_minor']) ? (int) $data['amount_minor'] : \App\Support\Money::toMinor((string) $data['amount']),
                'reference' => $data['reference'],
                'currency' => $currency,
                'callback_url' => $data['callback_url'] ?? null,
                'metadata' => $data['metadata'] ?? [
                    'user_id' => $data['user_id'] ?? null,
                    'order_id' => $data['order_id'] ?? null,
                    'type' => $data['type'] ?? 'payment'
                ],
                'channels' => $data['channels'] ?? ['card', 'bank', 'ussd', 'qr', 'mobile_money', 'bank_transfer']
            ]);
        } catch (ConnectionException $exception) {
            return $this->failure('PAYMENT_INITIALIZATION_OUTCOME_UNKNOWN', false, true);
        }

        if ($response->failed()) {
            Log::error('Paystack initialization failed', [
                'http_status' => $response->status(),
            ]);
            
            return [
                'status' => false,
                'message' => $response->status() === 429
                    ? 'The payment service is temporarily busy. Please try again shortly.'
                    : 'We could not start the payment right now. Please try again.',
                'code' => $response->status() === 429 ? 'PAYMENT_GATEWAY_RATE_LIMITED' : 'PAYMENT_GATEWAY_REJECTED',
                'retryable' => false,
                'outcome_unknown' => $response->serverError(),
            ];
        }

        $responseData = $response->json();
        if (data_get($responseData, 'status') !== true
            || data_get($responseData, 'data.reference') !== ($data['reference'] ?? null)
            || ! is_string(data_get($responseData, 'data.access_code'))
            || ! is_string(data_get($responseData, 'data.authorization_url'))
            || ! str_starts_with(data_get($responseData, 'data.authorization_url', ''), 'https://checkout.paystack.com/')) {
            return $this->failure('PAYMENT_INITIALIZATION_INVALID_RESPONSE', false, true);
        }

        return [
            'status' => true,
            'authorization_url' => $responseData['data']['authorization_url'],
            'access_code' => $responseData['data']['access_code'],
            'reference' => $responseData['data']['reference'],
        ];
    }

    public function verifyPayment(string $reference): array
    {
        if (! $this->isConfigured() || $reference === '') return $this->failure('PAYMENT_GATEWAY_NOT_CONFIGURED', false);
        try {
            $response = $this->client()
                ->get($this->baseUrl . '/transaction/verify/' . rawurlencode($reference));
        } catch (ConnectionException $exception) {
            return $this->failure('PAYMENT_VERIFICATION_UNAVAILABLE', true);
        }

        if ($response->failed()) {
            Log::error('Paystack verification failed', [
                'reference' => $reference,
                'http_status' => $response->status(),
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
        if (data_get($responseData, 'status') !== true || ! is_array(data_get($responseData, 'data'))
            || data_get($responseData, 'data.reference') !== $reference
            || ! is_string(data_get($responseData, 'data.status'))
            || ! is_int(data_get($responseData, 'data.amount'))
            || data_get($responseData, 'data.amount') <= 0
            || ! is_string(data_get($responseData, 'data.currency'))) {
            return $this->failure('PAYMENT_VERIFICATION_INVALID_RESPONSE', true);
        }

        return [
            'status' => true,
            'data' => $responseData['data'],
            'paid' => $responseData['data']['status'] === 'success',
        ];
    }

    public function refund(string $transactionId, ?int $amountMinor = null): array
    {
        if (! $this->isConfigured() || $transactionId === '' || ($amountMinor !== null && $amountMinor <= 0)) {
            return $this->failure('REFUND_INVALID_REQUEST', false);
        }
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
                'exception_class' => $exception::class,
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
            ]);

            return [
                'status' => false,
                'message' => 'Paystack rejected the refund request. No local refund was posted.',
                'code' => 'REFUND_REQUEST_FAILED',
                'retryable' => false,
                'outcome_unknown' => $response->serverError(),
            ];
        }

        $data = (array) ($response->json('data') ?? []);
        if ($response->json('status') !== true || $data === []) {
            return $this->failure('REFUND_INVALID_RESPONSE', false, true);
        }

        return [
            'status' => true,
            'data' => $data,
            'outcome_unknown' => false,
        ];
    }

    public function getStatus(string $reference): string
    {
        $result = $this->verifyPayment($reference);
        return ($result['status'] ?? false) ? $result['data']['status'] : 'unknown';
    }

    public function verifyRefund(string $refundId): array
    {
        if (! $this->isConfigured() || ! ctype_digit($refundId)) return $this->failure('REFUND_INVALID_REFERENCE', false);
        try {
            $response = $this->client()->get($this->baseUrl.'/refund/'.rawurlencode($refundId));
        } catch (ConnectionException $exception) {
            return $this->failure('REFUND_VERIFICATION_UNAVAILABLE', true);
        }
        if (! $response->successful() || $response->json('status') !== true || ! is_array($response->json('data'))
            || (string) $response->json('data.id') !== $refundId) {
            return $this->failure('REFUND_VERIFICATION_INVALID_RESPONSE', true);
        }
        return ['status' => true, 'data' => $response->json('data')];
    }

    public function validateWebhook(string $payload, ?string $signature = null): bool
    {
        if (! is_string($signature) || ! preg_match('/^[a-f0-9]{128}$/', $signature)) return false;
        // Outstanding payments keep their original environment after an admin
        // switches modes. Settlement still verifies the saved reference/mode.
        foreach (['test', 'live'] as $mode) {
            $key = $this->credentials['secret_key_'.$mode] ?? '';
            if (is_string($key) && str_starts_with($key, 'sk_'.$mode.'_')
                && hash_equals(hash_hmac('sha512', $payload, $key), $signature)) return true;
        }
        return false;
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
        $isLive = $this->settings['environment'] ?? 'test';
        $keyField = $isLive === 'live' ? 'secret_key_live' : 'secret_key_test';
        
        return $this->credentials[$keyField] ?? '';
    }

    public function getPublicKey(): string
    {
        $isLive = $this->settings['environment'] ?? 'test';
        $keyField = $isLive === 'live' ? 'public_key_live' : 'public_key_test';
        
        return $this->credentials[$keyField] ?? '';
    }

    public function isConfigured(): bool
    {
        $mode = $this->settings['environment'] ?? 'test';
        return in_array($mode, ['test', 'live'], true)
            && str_starts_with($this->getSecretKey(), 'sk_'.$mode.'_')
            && strlen($this->getSecretKey()) > strlen('sk_'.$mode.'_');
    }

    private function failure(string $code, bool $retryable, bool $unknown = false): array
    {
        return [
            'status' => false,
            'code' => $code,
            'message' => $unknown
                ? 'The payment provider returned no conclusive result. Check this payment before starting another attempt.'
                : 'The payment service could not complete this request. Please check the configuration or try verification again shortly.',
            'retryable' => $retryable,
            'outcome_unknown' => $unknown,
        ];
    }
}
