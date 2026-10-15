<?php

namespace App\Enums;

enum GroupMemberRole: string
{
    case LEADER = 'leader';
    case MEMBER = 'member';

    public function label(): string
    {
        return match ($this) {
            self::LEADER => 'Group Leader',
            self::MEMBER => 'Member',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
