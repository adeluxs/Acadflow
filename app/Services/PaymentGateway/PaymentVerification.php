<?php

declare(strict_types=1);

namespace App\Services\PaymentGateway;

final class PaymentVerification
{
    public static function matches(array $verification, string $reference, int $amountMinor, string $currency): bool
    {
        $data = $verification['data'] ?? [];

        return ($verification['status'] ?? false) === true
            && ($verification['paid'] ?? false) === true
            && ($data['status'] ?? null) === 'success'
            && $reference !== '' && ($data['reference'] ?? null) === $reference
            && $amountMinor > 0 && is_int($data['amount'] ?? null) && $data['amount'] === $amountMinor
            && strtoupper((string) ($data['currency'] ?? '')) === strtoupper($currency);
    }
}
