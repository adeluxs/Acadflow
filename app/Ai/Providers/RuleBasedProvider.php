<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

/**
 * The Rule-Based provider. Implements the same interface as external providers
 * so the router can treat it interchangeably. Uses the RuleEngine under the hood.
 */
class RuleBasedProvider implements AiProviderInterface
{
    public function __construct(protected RuleEngine $engine) {}

    public function name(): string
    {
        return 'rule_based';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function handle(string $feature, array $payload): AiResponse
    {
        return $this->engine->run($feature, $payload);
    }
}
