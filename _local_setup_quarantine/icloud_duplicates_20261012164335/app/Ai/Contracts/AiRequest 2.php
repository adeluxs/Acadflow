<?php

namespace App\Ai\Contracts;

use App\Models\User;

/**
 * Normalized internal request envelope for AcadFlow AI operations.
 *
 * Feature services provide business context only. Provider/model routing is not
 * accepted from arbitrary callers; it is resolved centrally from AI Settings so
 * a controller/job cannot bypass administrator configuration.
 */
final readonly class AiRequest
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $feature,
        public array $payload,
        public ?User $user = null,
        public ?string $scope = null,
    ) {}

    public function universityId(): ?int
    {
        return $this->user?->university_id;
    }
}
