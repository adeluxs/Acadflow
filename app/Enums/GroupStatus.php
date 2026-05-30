<?php

namespace App\Enums;

enum GroupStatus: string
{
    case FORMING = 'forming';
    case COMPLETE = 'complete';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::FORMING => 'Forming',
            self::COMPLETE => 'Complete',
            self::ARCHIVED => 'Archived',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
