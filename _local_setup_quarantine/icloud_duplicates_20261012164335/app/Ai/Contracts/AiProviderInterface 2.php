<?php

namespace App\Ai\Contracts;

/**
 * Provider-independent contract for every AcadFlow AI engine.
 *
 * Feature modules never depend on concrete provider SDKs. Provider-specific
 * networking belongs in adapters and provider selection belongs in AiRouter.
 */
interface AiProviderInterface
{
    public function name(): string;

    public function model(): ?string;

    /** @return list<string> */
    public function capabilities(): array;

    public function isAvailable(): bool;

    public function handle(string $feature, array $payload): AiResponse;

    /**
     * Run a small real connectivity check without exposing provider secrets.
     *
     * @return array{status:string,provider:string,message:string,checked_at:string,model?:string,latency_ms?:int,error_code?:string}
     */
    public function healthCheck(): array;
}
