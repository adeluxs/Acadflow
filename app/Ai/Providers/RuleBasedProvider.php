<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;

class RuleBasedProvider implements AiProviderInterface
{
    public function __construct(protected RuleEngine $engine) {}

    public function name(): string { return 'rule_based'; }
    public function model(): ?string { return 'rule-engine'; }
    public function capabilities(): array { return ['rules', 'validation']; }
    public function isAvailable(): bool { return true; }

    public function handle(string $feature, array $payload): AiResponse
    {
        return $this->engine->run($feature, $payload)->withData([
            'provider' => null,
            'model' => 'rule-engine',
        ]);
    }

    public function healthCheck(): array
    {
        return [
            'status' => 'healthy',
            'provider' => 'rule_based',
            'model' => 'rule-engine',
            'message' => 'Local rule engine is available.',
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
