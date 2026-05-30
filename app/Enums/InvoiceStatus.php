<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case WAIVED = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::WAIVED => 'Waived',
        };
    }
}

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::VERIFIED => 'Verified',
            self::FAILED => 'Failed',
        };
    }
}

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
}

enum BillingModel: string
{
    case INSTITUTION = 'institution';
    case STUDENT = 'student';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::INSTITUTION => 'Institution Paid',
            self::STUDENT => 'Student Paid',
            self::HYBRID => 'Hybrid',
        };
    }
}
