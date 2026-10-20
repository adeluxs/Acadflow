<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    protected PaymentGatewayManager $gatewayManager;

    public function __construct(PaymentGatewayManager $gatewayManager)
    {
        $this->gatewayManager = $gatewayManager;
    }

    /**
     * List all payment gateways
     */
    public function index()
    {
        $gateways = PaymentGateway::orderBy('sort_order')->get();
        foreach ($gateways as $gateway) {
            $gateway->setAttribute('is_configured', in_array($gateway->code, $this->gatewayManager->registeredCodes(), true) && $this->gatewayManager->gateway($gateway->code)->isConfigured());
        }
        
        return view('admin.payment-gateways.index', compact('gateways'));
    }

    /**
     * Show form to create new gateway
     */
    public function create()
    {
        $gatewayTypes = [
            'paystack' => 'Paystack',
            // Future gateways: 'stripe' => 'Stripe', 'flutterwave' => 'Flutterwave', etc.
        ];

        return view('admin.payment-gateways.create', compact('gatewayTypes'));
    }

    /**
     * Store new payment gateway
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'unique:payment_gateways,code', Rule::in($this->gatewayManager->registeredCodes())],
            'name' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $validated['is_active'] = false; // Activate only after credentials are configured.
        $validated['is_test_mode'] = $request->boolean('is_test_mode', true);
        $gateway = PaymentGateway::create($validated);

        return redirect()->route('admin.payment-gateways.edit', $gateway)
            ->with('success', 'Payment gateway created. Please configure the credentials.');
    }

    /**
     * Show form to edit gateway
     */
    public function edit(PaymentGateway $paymentGateway)
    {
        $gateway = $this->gatewayManager->gateway($paymentGateway->code);
        $configFields = $gateway->getConfigFields();
        
        $credentials = $paymentGateway->credentials ?? [];

        return view('admin.payment-gateways.edit', compact('paymentGateway', 'configFields', 'credentials'));
    }

    /**
     * Update gateway configuration
     */
    public function update(Request $request, PaymentGateway $paymentGateway)
    {
        $gateway = $this->gatewayManager->gateway($paymentGateway->code);
        $configFields = $gateway->getConfigFields();

        $rules = [
            'is_active' => ['sometimes', 'boolean'],
            'environment' => ['required', Rule::in(['test', 'live'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'supported_currencies' => ['required', 'array', 'min:1'],
            'supported_currencies.*' => ['string', 'distinct', Rule::in($gateway->getSupportedCurrencies())],
        ];
        foreach ($configFields as $key => $field) {
            if (in_array($field['type'], ['text', 'password'], true)) {
                $rules[$key] = ['nullable', 'string', 'max:255'];
            }
        }
        $validated = $request->validate($rules);
        $credentials = $paymentGateway->credentials ?? [];
        foreach ($configFields as $key => $field) {
            if (in_array($field['type'], ['text', 'password'], true) && $request->filled($key)) {
                // The model encrypts the entire credential array at rest.
                $credentials[$key] = $validated[$key];
            }
        }
        $mode = $validated['environment'];
        $secret = $credentials['secret_key_'.$mode] ?? '';
        try { $secret = Crypt::decryptString($secret); } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {}
        if ($request->boolean('is_active') && (! str_starts_with($secret, 'sk_'.$mode.'_') || strlen($secret) <= strlen('sk_'.$mode.'_'))) {
            throw ValidationException::withMessages(['secret_key_'.$mode => 'Enter a valid '.$mode.' secret key before activating this gateway.']);
        }
        $paymentGateway->update([
            'is_active' => $request->boolean('is_active'),
            'is_test_mode' => $mode === 'test',
            'sort_order' => $validated['sort_order'] ?? $paymentGateway->sort_order,
            'credentials' => $credentials,
            'settings' => array_merge($paymentGateway->settings ?? [], [
                'environment' => $mode,
                'supported_currencies' => $validated['supported_currencies'],
            ]),
        ]);

        return redirect()->route('admin.payment-gateways.index')
            ->with('success', 'Payment gateway updated successfully.');
    }

    /**
     * Delete gateway
     */
    public function destroy(PaymentGateway $paymentGateway)
    {
        if ($paymentGateway->is_active) {
            return back()->with('error', 'Cannot delete active payment gateway. Please deactivate it first.');
        }

        if ($paymentGateway->transactions()->exists() || \App\Models\Payment::query()->whereNotNull('reference')->exists()) {
            return back()->with('error', 'Gateway payment history must be preserved. Keep this gateway deactivated.');
        }

        $paymentGateway->delete();

        return redirect()->route('admin.payment-gateways.index')
            ->with('success', 'Payment gateway deleted.');
    }

    /**
     * Test gateway connection
     */
    public function testConnection(Request $request, PaymentGateway $paymentGateway)
    {
        $gateway = $this->gatewayManager->gateway($paymentGateway->code);
        
        if (!$gateway->isConfigured()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gateway is not configured properly.',
            ], 400);
        }

        try {
            // Try a simple API call to test connection
            // For Paystack, we can check account details
            if ($paymentGateway->code === 'paystack') {
                $response = \Illuminate\Support\Facades\Http::withToken($gateway->getSecretKey())
                    ->acceptJson()
                    ->connectTimeout((int) config('services.paystack.connect_timeout', 8))
                    ->timeout((int) config('services.paystack.timeout', 20))
                    ->get('https://api.paystack.co/integration/payment_session_timeout');

                if ($response->successful() && $response->json('status') === true) {
                    return response()->json([
                        'status' => 'success',
                        'success' => true,
                        'message' => 'Gateway connection successful.',
                        'request_id' => UserFacingError::requestId($request),
                    ]);
                }

                Log::warning('Payment gateway connection test was rejected by provider.', [
                    'request_id' => UserFacingError::requestId($request),
                    'gateway_id' => $paymentGateway->id,
                    'gateway_code' => $paymentGateway->code,
                    'http_status' => $response->status(),
                    'provider_response' => $response->json(),
                ]);

                $retryable = $response->status() === 429 || $response->serverError();
                $message = match (true) {
                    in_array($response->status(), [401, 403], true) => 'The gateway rejected the configured credentials. Please review the gateway settings.',
                    $response->status() === 429 => 'The gateway is temporarily busy. Please try the connection test again shortly.',
                    $response->serverError() => 'The gateway is temporarily unavailable. Please try the connection test again.',
                    default => 'The gateway connection test was not accepted. Please review the gateway configuration.',
                };

                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'code' => 'PAYMENT_GATEWAY_TEST_FAILED',
                    'message' => $message,
                    'retryable' => $retryable,
                    'request_id' => UserFacingError::requestId($request),
                ], $retryable ? 503 : 422);
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Gateway configured successfully.',
                'request_id' => UserFacingError::requestId($request),
            ]);
        } catch (\Throwable $e) {
            $safe = UserFacingError::fromThrowable($e, $request);
            Log::warning('Payment gateway connection test failed.', [
                'request_id' => $safe->requestId,
                'gateway_id' => $paymentGateway->id,
                'gateway_code' => $paymentGateway->code,
                'exception_class' => $e::class,
                'internal_message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'success' => false,
                'code' => $safe->code,
                'message' => 'The gateway connection could not be verified right now. Please try again.',
                'retryable' => true,
                'request_id' => $safe->requestId,
            ], 503);
        }
    }
}
