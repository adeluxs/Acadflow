<?php

namespace App\Services\Discovery;

class LocalEmbeddingService
{
    public function __construct(private readonly int $dimensions = 64) {}

    /** @return list<float> */
    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            $hash = hash('sha256', $token, true);
            for ($i = 0; $i < min(8, strlen($hash)); $i++) {
                $index = ord($hash[$i]) % $this->dimensions;
                $vector[$index] += (($i % 2) === 0 ? 1.0 : -1.0) * (1.0 / max(1, mb_strlen($token)));
            }
        }

        $magnitude = sqrt(array_sum(array_map(fn (float $value): float => $value * $value, $vector)));
        if ($magnitude > 0) {
            $vector = array_map(fn (float $value): float => round($value / $magnitude, 8), $vector);
        }

        return $vector;
    }

    /** @param list<float>|null $left @param list<float>|null $right */
    public function cosine(?array $left, ?array $right): float
    {
        if (! $left || ! $right || count($left) !== count($right)) {
            return 0.0;
        }

        $dot = $leftMagnitude = $rightMagnitude = 0.0;
        foreach ($left as $index => $value) {
            $rightValue = (float) $right[$index];
            $dot += (float) $value * $rightValue;
            $leftMagnitude += (float) $value * (float) $value;
            $rightMagnitude += $rightValue * $rightValue;
        }

        return ($leftMagnitude > 0 && $rightMagnitude > 0)
            ? $dot / (sqrt($leftMagnitude) * sqrt($rightMagnitude))
            : 0.0;
    }
}
