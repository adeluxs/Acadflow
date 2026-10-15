<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes(max(16, $bytes)));
    }

    public function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'AcadFlow'));
        $label = rawurlencode((string) config('app.name', 'AcadFlow').':'.$user->email);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function verifyUserCode(User $user, string $code, bool $consumeRecoveryCode = true): bool
    {
        $code = preg_replace('/\s+/', '', trim($code));
        if ($code === '') return false;

        $secret = $this->decryptSecret($user->two_factor_secret);
        if ($secret !== null && preg_match('/^\d{6}$/', $code) && $this->verify($secret, $code)) {
            return true;
        }

        // Backward compatibility for installations that stored a hashed static
        // challenge code before TOTP provisioning was added.
        if ($secret === null && is_string($user->two_factor_secret) && Hash::check($code, $user->two_factor_secret)) {
            return true;
        }

        $recoveryCodes = collect($user->two_factor_recovery_codes ?? []);
        $match = $recoveryCodes->search(fn ($hash): bool => is_string($hash) && Hash::check($code, $hash));
        if ($match === false) return false;

        if ($consumeRecoveryCode) {
            $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes->forget($match)->values()->all()])->save();
        }

        return true;
    }

    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) return true;
        }
        return false;
    }

    /** @return array{plain: array<int,string>, hashed: array<int,string>} */
    public function recoveryCodes(int $count = 8): array
    {
        $plain = collect(range(1, max(6, $count)))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->values()
            ->all();

        return ['plain' => $plain, 'hashed' => array_map(fn (string $code): string => Hash::make($code), $plain)];
    }

    private function decryptSecret(?string $value): ?string
    {
        if (! $value) return null;
        try {
            $secret = Crypt::decryptString($value);
            return preg_match('/^[A-Z2-7]+=*$/', $secret) ? $secret : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function code(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $high = intdiv($counter, 0x100000000);
        $low = $counter % 0x100000000;
        $binaryCounter = pack('N2', $high, $low);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) return '';
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $decoded .= chr(bindec($byte));
        }
        return $decoded;
    }
}
