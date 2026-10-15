<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Crypt;

abstract class AbstractGateway implements GatewayInterface
{
    protected array $credentials = [];
    protected array $settings = [];

    abstract public function getCode(): string;
    abstract public function getName(): string;

    public function setCredentials(array $credentials): void
    {
        $this->credentials = $credentials;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    public function isConfigured(): bool
    {
        return !empty($this->credentials);
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'NGN'];
    }

    protected function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    protected function decrypt(string $value): string
    {
        return Crypt::decryptString($value);
    }
}
