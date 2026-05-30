<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

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
    public function testConnection(PaymentGateway $paymentGateway)
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
                    ->get('https://api.paystack.co/integration/payment_session_timeout');
                
                if ($response->successful()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Gateway connection successful!',
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Gateway configured successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
