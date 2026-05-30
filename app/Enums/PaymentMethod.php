<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case CARD = 'card';
    case WALLET = 'wallet';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CARD => 'Card Payment',
            self::WALLET => 'Wallet',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
