<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case PRESENT = 'present';
    case LATE = 'late';
    case ABSENT = 'absent';
    case INVALID = 'invalid';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::PRESENT => 'Present',
            self::LATE => 'Late',
            self::ABSENT => 'Absent',
            self::INVALID => 'Invalid',
            self::PENDING => 'Pending',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
