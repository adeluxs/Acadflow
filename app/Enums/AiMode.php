<?php

namespace App\Enums;

/**
 * Modes supported by the AI Router.
 */
enum AiMode: string
{
    case RULE_BASED = 'rule_based';
    case PROVIDER = 'provider';
    case HYBRID = 'hybrid';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::RULE_BASED => 'Rule-Based Only',
            self::PROVIDER => 'AI Provider Only',
            self::HYBRID => 'Hybrid Mode',
            self::DISABLED => 'Disabled',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
