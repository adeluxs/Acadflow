<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit money helper.
 *
 * Financial domain code must never use binary floating point. Inputs from HTTP
 * forms are parsed as decimal strings and stored/processed as integer minor
 * units (kobo for NGN, cents for two-decimal currencies).
 */
final class Money
{
    public static function toMinor(string|int $amount, int $scale = 2): int
    {
        if (is_int($amount)) {
            return $amount * (10 ** $scale);
        }

        $value = trim(str_replace([',', ' '], '', $amount));
        if ($value === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        if (strlen($fraction) > $scale) {
            $discarded = substr($fraction, $scale);
            if (trim($discarded, '0') !== '') {
                throw new InvalidArgumentException('Amount has more decimal places than the currency supports.');
            }
            $fraction = substr($fraction, 0, $scale);
        }

        $fraction = str_pad($fraction, $scale, '0');
        $minor = ((int) $whole * (10 ** $scale)) + (int) ($fraction === '' ? '0' : $fraction);

        return $negative ? -$minor : $minor;
    }

    /**
     * Parse a decimal amount and round half-up to the requested scale without
     * ever converting through binary floating point. Useful for provider costs
     * that may report more precision than the storage unit (for example micro-USD).
     */
    public static function toMinorRounded(string|int $amount, int $scale = 2): int
    {
        if (is_int($amount)) return $amount * (10 ** $scale);

        $value = trim(str_replace([',', ' '], '', $amount));
        if ($value === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $kept = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $minor = ((int) $whole * (10 ** $scale)) + (int) ($kept === '' ? '0' : $kept);
        $nextDigit = strlen($fraction) > $scale ? (int) $fraction[$scale] : 0;
        if ($nextDigit >= 5) $minor++;

        return $negative ? -$minor : $minor;
    }

    public static function fromMinor(int $minor, int $scale = 2): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $factor = 10 ** $scale;
        $whole = intdiv($minor, $factor);
        $fraction = str_pad((string) ($minor % $factor), $scale, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.($scale > 0 ? '.'.$fraction : '');
    }

    public static function percentage(int $amountMinor, int $basisPoints): int
    {
        if ($amountMinor < 0 || $basisPoints < 0) {
            throw new InvalidArgumentException('Money and percentage values must not be negative.');
        }

        // Half-up rounding without floating point. 10,000 basis points = 100%.
        return intdiv(($amountMinor * $basisPoints) + 5_000, 10_000);
    }

    public static function clampBasisPoints(int $basisPoints): int
    {
        return max(0, min(10_000, $basisPoints));
    }
}
