<?php

namespace App\Enums;

enum SubmissionType: string
{
    case ASSIGNMENT = 'assignment';
    case PROJECT = 'project';
    case SIWES = 'siwes';
    case GROUP = 'group';
    case SEMINAR = 'seminar';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNMENT => 'Assignment',
            self::PROJECT => 'Project',
            self::SIWES => 'SIWES Report',
            self::GROUP => 'Group Project',
            self::SEMINAR => 'Seminar Paper',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
