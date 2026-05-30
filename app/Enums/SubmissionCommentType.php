<?php

namespace App\Enums;

enum SubmissionCommentType: string
{
    case GENERAL = 'general';
    case CORRECTION = 'correction';
    case SUGGESTION = 'suggestion';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::CORRECTION => 'Correction',
            self::SUGGESTION => 'Suggestion',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
