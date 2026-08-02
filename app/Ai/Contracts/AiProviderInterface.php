<?php

namespace App\Ai\Contracts;

/**
 * Every AI provider (rule-based or external) implements this interface.
 *
 * Feature modules MUST NOT depend on any concrete provider; they only depend on
 * this interface, resolved via the AiManager / AiRouter.
 */
interface AiProviderInterface
{
    /**
     * Unique machine name of the provider (matches AiProviderName value).
     */
    public function name(): string;

    /**
     * Whether this provider is available / configured.
     */
    public function isAvailable(): bool;

    /**
     * Process a generic request payload.
     *
     * @param  string  $feature  The feature key (e.g. 'submission_validator')
     * @param  array  $payload  Feature-specific input (document text, metadata, etc.)
     */
    public function handle(string $feature, array $payload): AiResponse;
}
