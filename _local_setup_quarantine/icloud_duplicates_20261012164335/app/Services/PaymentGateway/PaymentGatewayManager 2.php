<?php

namespace App\Services\PaymentGateway;

use App\Models\PaymentGateway;
use Illuminate\Support\Facades\App;

class PaymentGatewayManager
{
    private array $gateways = [];
    private ?string $defaultGateway = null;

    public function __construct()
    {
        $this->registerGateway('paystack', PaystackGateway::class);
        // Future gateways can be registered here
        // $this->registerGateway('stripe', StripeGateway::class);
        // $this->registerGateway('flutterwave', FlutterwaveGateway::class);
    }

    public function registerGateway(string $code, string $gatewayClass): void
    {
        if (!class_exists($gatewayClass)) {
            throw new \InvalidArgumentException("Gateway class {$gatewayClass} does not exist");
        }

        $this->gateways[$code] = $gatewayClass;
    }

    public function gateway(?string $code = null): GatewayInterface
    {
        $code = $code ?? $this->getDefaultGateway();
        
        if (!isset($this->gateways[$code])) {
            throw new \InvalidArgumentException("Gateway {$code} is not registered");
        }

        $gateway = App::make($this->gateways[$code]);
        
        $this->configureGateway($gateway, $code);
        
        return $gateway;
    }

    public function getDefaultGateway(): string
    {
        if ($this->defaultGateway) {
            return $this->defaultGateway;
        }

        // Get default from database settings
        $defaultGateway = PaymentGateway::where('is_active', true)
            ->where('is_test_mode', !app()->environment('production'))
            ->orderBy('sort_order')
            ->first();

        return $this->defaultGateway = $defaultGateway?->code ?? 'paystack';
    }

    public function setDefaultGateway(string $code): void
    {
        $this->defaultGateway = $code;
    }

    public function getAvailableGateways(): array
    {
        return PaymentGateway::where('is_active', true)->get()->map(function ($gateway) {
            return [
                'code' => $gateway->code,
                'name' => $gateway->name,
                'description' => $gateway->description,
                'is_configured' => $this->gateway($gateway->code)->isConfigured(),
            ];
        })->toArray();
    }

    protected function configureGateway(GatewayInterface $gateway, string $code): void
    {
        $gatewayModel = PaymentGateway::where('code', $code)->first();
        
        if ($gatewayModel) {
            $credentials = $gatewayModel->credentials ?? [];
            $settings = $gatewayModel->settings ?? [];
            
            // Decrypt sensitive credentials
            foreach ($credentials as $key => $value) {
                if (is_string($value) && str_contains($key, 'secret')) {
                    try {
                        $credentials[$key] = decrypt($value);
                    } catch (\Exception $e) {
                        // Keep as is if not encrypted
                    }
                }
            }
            
            $gateway->setCredentials($credentials);
            $gateway->setSettings($settings);
        }
    }

    public function gatewayForTransaction($transactionable): GatewayInterface
    {
        if (is_object($transactionable)) {
            // Check if transactionable has gateway information
            if (method_exists($transactionable, 'paymentGateway')) {
                $gateway = $transactionable->paymentGateway;
                if ($gateway) {
                    return $this->gateway($gateway->code);
                }
            }
            
            // Check if has gateway_code attribute
            if (property_exists($transactionable, 'gateway_code') && $transactionable->gateway_code) {
                return $this->gateway($transactionable->gateway_code);
            }
        }
        
        return $this->gateway();
    }
}
