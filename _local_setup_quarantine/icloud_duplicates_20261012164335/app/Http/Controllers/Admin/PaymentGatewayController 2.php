<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Support\Errors\UserFacingError;
use Illuminate\Support\Facades\Log;

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
            'code' => 'required|string|unique:payment_gateways,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'sort_order' => 'integer',
        ]);

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
        
        // Decrypt credentials for display
        $credentials = $paymentGateway->credentials ?? [];
        foreach ($credentials as $key => $value) {
            if (is_string($value) && str_contains($key, 'secret')) {
                try {
                    $credentials[$key] = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    // Keep as is
                }
            }
        }

        return view('admin.payment-gateways.edit', compact('paymentGateway', 'configFields', 'credentials'));
    }

    /**
     * Update gateway configuration
     */
    public function update(Request $request, PaymentGateway $paymentGateway)
    {
        $gateway = $this->gatewayManager->gateway($paymentGateway->code);
        $configFields = $gateway->getConfigFields();

        // Build validation rules from config fields
        $rules = [
            'is_active' => 'boolean',
            'is_test_mode' => 'boolean',
            'sort_order' => 'integer',
        ];

        foreach ($configFields as $fieldKey => $field) {
            if ($field['required'] ?? false) {
                $rules[$fieldKey] = 'required';
            }
            
            if ($field['type'] === 'password') {
                $rules[$fieldKey] = ($rules[$fieldKey] ?? '') . '|nullable';
                // Only require if filling for first time
                if (empty($paymentGateway->credentials[$fieldKey] ?? null)) {
                    $rules[$fieldKey] = ($rules[$fieldKey] ?? '') . '|required';
                }
            }
        }

        $validated = $request->validate($rules);

        // Encrypt sensitive credentials
        $credentials = [];
        foreach ($configFields as $fieldKey => $field) {
            if (isset($validated[$fieldKey])) {
                if ($field['type'] === 'password' && $validated[$fieldKey]) {
                    $credentials[$fieldKey] = Crypt::encryptString($validated[$fieldKey]);
                } else {
                    $credentials[$fieldKey] = $validated[$fieldKey];
                }
            }
        }

        // Merge with existing credentials
        $existingCredentials = $paymentGateway->credentials ?? [];
        foreach ($configFields as $fieldKey => $field) {
            if ($field['type'] === 'password' && !isset($validated[$fieldKey])) {
                // Keep existing value if not provided
                if (isset($existingCredentials[$fieldKey])) {
                    $credentials[$fieldKey] = $existingCredentials[$fieldKey];
                }
            }
        }

        $settings = [];
        if (isset($validated['environment'])) {
            $settings['environment'] = $validated['environment'];
        }
        if (isset($validated['supported_currencies'])) {
            $settings['supported_currencies'] = $validated['supported_currencies'];
        }

        $paymentGateway->update([
            'is_active' => $validated['is_active'] ?? false,
            'is_test_mode' => $validated['is_test_mode'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'credentials' => $credentials,
            'settings' => $settings,
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

                if ($response->successful()) {
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
