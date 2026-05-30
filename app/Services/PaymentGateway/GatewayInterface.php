<?php

namespace App\Services\PaymentGateway;

interface GatewayInterface
{
    /**
     * Get gateway code (identifier)
     */
    public function getCode(): string;

    /**
     * Get gateway name
     */
    public function getName(): string;

    /**
     * Initialize payment
     */
    public function initializePayment(array $data): array;

    /**
     * Verify payment
     */
    public function verifyPayment(string $reference): array;

    /**
     * Process refund
     */
    public function refund(string $transactionId, float $amount = null): array;

    /**
     * Get payment status
     */
    public function getStatus(string $reference): string;

    /**
     * Validate webhook
     */
    public function validateWebhook(array $payload, ?string $signature = null): bool;

    /**
     * Get configuration form fields
     */
    public function getConfigFields(): array;

    /**
     * Set credentials
     */
    public function setCredentials(array $credentials): void;

    /**
     * Check if gateway is configured
     */
    public function isConfigured(): bool;

    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies(): array;
}
