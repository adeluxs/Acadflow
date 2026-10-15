<?php

declare(strict_types=1);

namespace App\Support\Security;

final class RetryAfter
{
    public static function secondsFromHeaders(array $headers, int $fallback = 60): int
    {
        $value = $headers['Retry-After'] ?? $headers['retry-after'] ?? $fallback;

        if (is_array($value)) {
            $value = $value[0] ?? $fallback;
        }

        if (is_numeric($value)) {
            return max(1, (int) ceil((float) $value));
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp !== false) {
            return max(1, $timestamp - time());
        }

        return max(1, $fallback);
    }

    public static function human(int $seconds): string
    {
        $seconds = max(1, $seconds);

        if ($seconds < 60) {
            return $seconds.' '.($seconds === 1 ? 'second' : 'seconds');
        }

        $minutes = (int) ceil($seconds / 60);

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }

    public static function message(string $lead, int $seconds): string
    {
        return rtrim(trim($lead), '.').'. Please try again in '.self::human($seconds).'.';
    }
}
