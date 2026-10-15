<?php

namespace App\Contracts\Scholarly;

interface ScholarlyProviderInterface
{
    public function name(): string;
    public function available(): bool;
    /** @return array<int,array<string,mixed>> */
    public function search(string $query, array $filters = [], int $limit = 20): array;
    /** @return array<string,mixed>|null */
    public function find(string $identifier): ?array;
}
