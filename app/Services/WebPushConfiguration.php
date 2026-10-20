<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\VAPID;

class WebPushConfiguration
{
    public function auth(): array
    {
        $public = (string) config('services.webpush.vapid_public_key', '');
        $private = (string) config('services.webpush.vapid_private_key', '');
        $subject = (string) config('services.webpush.vapid_subject', '');
        if ($public === '' && $private === '') {
            $public = (string) SettingService::getGlobal('webpush_vapid_public_key', '');
            $encrypted = (string) SettingService::getGlobal('webpush_vapid_private_key', '');
            $subject = (string) SettingService::getGlobal('webpush_vapid_subject', '');
            try { $private = $encrypted !== '' ? Crypt::decryptString($encrypted) : ''; }
            catch (\Illuminate\Contracts\Encryption\DecryptException $exception) { $private = ''; }
        }
        return ['subject' => $subject, 'publicKey' => $public, 'privateKey' => $private];
    }

    public function isConfigured(): bool
    {
        try {
            $auth = $this->auth();
            $subject = $auth['subject'];
            if (! (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL))
                && ! (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL))) return false;
            VAPID::validate($auth); return true;
        }
        catch (\Throwable $exception) { return false; }
    }

    public function generate(string $subject, int $actorId): void
    {
        if ($this->isConfigured()) throw ValidationException::withMessages(['push' => 'Push delivery keys already exist. Keep them to preserve current browser subscriptions.']);
        if (config('services.webpush.vapid_public_key') || config('services.webpush.vapid_private_key')) {
            throw ValidationException::withMessages(['push' => 'Complete or remove the server VAPID environment configuration before generating delivery keys here.']);
        }
        $keys = VAPID::createVapidKeys();
        try {
            if (! (str_starts_with($subject, 'mailto:') && filter_var(substr($subject, 7), FILTER_VALIDATE_EMAIL))
                && ! (str_starts_with($subject, 'https://') && filter_var($subject, FILTER_VALIDATE_URL))) throw new \InvalidArgumentException();
            VAPID::validate(['subject' => $subject] + $keys);
        }
        catch (\Throwable $exception) { throw ValidationException::withMessages(['subject' => 'Enter a mailto: contact email or a public HTTPS contact URL.']); }
        foreach (['webpush_vapid_public_key', 'webpush_vapid_private_key', 'webpush_vapid_subject'] as $key) {
            \App\Models\Setting::query()->firstOrCreate(['key' => $key], ['value' => '', 'type' => 'string', 'group' => 'webpush']);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($keys, $subject, $actorId): void {
            // Serialize concurrent setup requests without replacing a key pair.
            $setting = \App\Models\Setting::query()->where('key', 'webpush_vapid_public_key')->lockForUpdate()->firstOrFail();
            if ($setting->value !== '') throw ValidationException::withMessages(['push' => 'Delivery keys already exist. Restore their configuration instead of replacing them.']);
            SettingService::flushRequestCache();
            if ($this->isConfigured()) throw ValidationException::withMessages(['push' => 'Push delivery is already configured.']);
            SettingService::set('webpush_vapid_public_key', $keys['publicKey'], 'string', null, $actorId);
            SettingService::set('webpush_vapid_private_key', Crypt::encryptString($keys['privateKey']), 'string', null, $actorId);
            SettingService::set('webpush_vapid_subject', $subject, 'string', null, $actorId);
        });
    }
}
