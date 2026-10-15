<?php

namespace App\Enums;

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

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
