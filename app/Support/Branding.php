<?php

declare(strict_types=1);

namespace App\Support;

final class Branding
{
    public const NAME = 'AcadSyntra';

    /** Normalize application instructions, never user/retrieved document context. */
    public static function instruction(string $text): string
    {
        return preg_replace('/\b(?:AcadFlow|UniFlow)\b/u', self::NAME, $text) ?? $text;
    }
}
