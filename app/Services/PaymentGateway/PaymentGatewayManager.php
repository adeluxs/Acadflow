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
        if (!is_subclass_of($gatewayClass, GatewayInterface::class)) {
            throw new \InvalidArgumentException("Gateway class {$gatewayClass} does not exist");
        }

        $this->gateways[$code] = $gatewayClass;
    }

    public function registeredCodes(): array
    {
        return array_keys($this->gateways);
    }

    public function gateway(?string $code = null, ?string $environment = null): GatewayInterface
    {
        $code = $code ?? $this->getDefaultGateway();
        
        if (!isset($this->gateways[$code])) {
            throw new \InvalidArgumentException("Gateway {$code} is not registered");
        }

        $gateway = App::make($this->gateways[$code]);
        
        $this->configureGateway($gateway, $code);
        if ($environment !== null) {
            if (! in_array($environment, ['test', 'live'], true)) throw new \InvalidArgumentException('Invalid payment environment.');
            $settings = PaymentGateway::where('code', $code)->value('settings') ?? [];
            $gateway->setSettings(array_merge($settings, ['environment' => $environment]));
        }
        
        return $gateway;
    }

    public function getDefaultGateway(): string
    {
        if ($this->defaultGateway) {
            return $this->defaultGateway;
        }

        $gateway = $this->getCheckoutGateways()->first();
        return $gateway?->code ?? 'paystack';
    }

    public function setDefaultGateway(string $code): void
    {
        $this->defaultGateway = $code;
    }

    public function getAvailableGateways(): array
    {
        return $this->getCheckoutGateways()->map(function ($gateway) {
            return [
                'code' => $gateway->code,
                'name' => $gateway->name,
                'description' => $gateway->description,
                'is_configured' => $this->gateway($gateway->code)->isConfigured(),
            ];
        })->toArray();
    }

    public function getCheckoutGateways(): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentGateway::query()->where('is_active', true)->whereIn('code', $this->registeredCodes())
            ->orderBy('sort_order')->get()->filter(fn ($gateway) => $this->gateway($gateway->code)->isConfigured())->values();
    }

    protected function configureGateway(GatewayInterface $gateway, string $code): void
    {
        $gatewayModel = PaymentGateway::where('code', $code)->first();
        
        if ($gatewayModel) {
            $credentials = $gatewayModel->credentials ?? [];
            $settings = $gatewayModel->settings ?? [];
            // One persisted mode controls both checkout and signature verification.
            $settings['environment'] = $gatewayModel->is_test_mode ? 'test' : 'live';
            
            // Decrypt sensitive credentials
            foreach ($credentials as $key => $value) {
                if (is_string($value) && str_contains($key, 'secret')) {
                    try {
                        $credentials[$key] = \Illuminate\Support\Facades\Crypt::decryptString($value);
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
                    return $this->gateway($gateway->code, $transactionable->metadata['gateway_environment'] ?? null);
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
