<?php

declare(strict_types=1);

namespace App\Support;

final class PushEndpoint
{
    public static function valid(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) return false;
        $host = strtolower($parts['host'] ?? '');
        foreach (config('services.webpush.allowed_hosts', []) as $allowed) {
            if ($host === $allowed || (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1)))) return true;
        }
        return false;
    }

    public static function validKeys(array $keys): bool
    {
        $public = self::decode((string) ($keys['p256dh'] ?? ''));
        $auth = self::decode((string) ($keys['auth'] ?? ''));
        if ($public === false || strlen($public) !== 65 || $public[0] !== "\x04" || $auth === false || strlen($auth) !== 16) return false;
        $spki = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200').$public;
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----";
        return @openssl_pkey_get_public($pem) !== false;
    }

    public static function decode(string $key): string|false
    {
        if (! preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $key)) return false;
        return base64_decode(strtr($key, '-_', '+/'), true);
    }
}
