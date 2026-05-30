<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case ENROLLED = 'enrolled';
    case DROPPED = 'dropped';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::ENROLLED => 'Enrolled',
            self::DROPPED => 'Dropped',
            self::COMPLETED => 'Completed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
