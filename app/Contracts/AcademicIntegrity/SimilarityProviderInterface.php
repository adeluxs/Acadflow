<?php

namespace App\Contracts\AcademicIntegrity;

interface SimilarityProviderInterface
{
    public function name(): string;
    public function available(): bool;

    /** @return array{score:float,summary:string,matches:array<int,array<string,mixed>>,metadata?:array<string,mixed>} */
    public function compare(string $text, array $context = []): array;
}
