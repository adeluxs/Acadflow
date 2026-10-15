<?php

namespace App\Http\Middleware;

use App\Services\PaymentGateway\PaymentGatewayManager;
use Closure;
use Illuminate\Http\Request;

class VerifyPaymentWebhook
{
    public function __construct(private PaymentGatewayManager $gatewayManager)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $gatewayCode = $request->route('gateway');
        
        try {
            $gateway = $this->gatewayManager->gateway($gatewayCode);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid gateway'], 400);
        }

        // Validate webhook signature
        $signature = $request->header('X-Paystack-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-Signature');
        if (!$gateway->validateWebhook($request->all(), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $request->attributes->set('payment_gateway', $gateway);

        return $next($request);
    }
}
