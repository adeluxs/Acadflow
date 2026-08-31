<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'central password policy' => ['app/Services/SettingService.php', 'getPasswordPolicy'],
    'central password rules' => ['app/Services/SettingService.php', 'getPasswordRules'],
    'admin-controlled auth limits' => ['app/Providers/AppServiceProvider.php', 'getSecurityRateLimits'],
    'structured 429 response' => ['bootstrap/app.php', "'retry_after' => \$retryAfter"],
    'password policy public API' => ['app/Http/Controllers/SettingsController.php', "'password_policy' => SettingService::getPasswordPolicy()"],
    'registration live checklist' => ['resources/views/auth/register.blade.php', "auth.partials.password-policy"],
    'reset live checklist' => ['resources/views/auth/reset-password.blade.php', "auth.partials.password-policy"],
    'password policy UI module' => ['resources/js/password-policy.js', 'initPasswordPolicies'],
    'rate limit countdown UI' => ['resources/js/rate-limit-feedback.js', 'initRateLimitFeedback'],
    'security settings migration' => ['database/migrations/2026_08_20_150000_add_security_rate_limit_settings.php', 'insertOrIgnore'],
];

$failures = [];
foreach ($checks as $label => [$relative, $needle]) {
    $path = $root.DIRECTORY_SEPARATOR.$relative;
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false || ! str_contains($content, $needle)) {
        $failures[] = $label.' ('.$relative.')';
    }
}

$resetController = file_get_contents($root.'/app/Http/Controllers/Auth/NewPasswordController.php') ?: '';
if (str_contains($resetController, 'PasswordRule::min(')) {
    $failures[] = 'password reset still contains a hardcoded Laravel password rule';
}

$provider = file_get_contents($root.'/app/Providers/AppServiceProvider.php') ?: '';
foreach (['Limit::perMinute(10)', 'Limit::perHour(5)'] as $hardcoded) {
    if (str_contains($provider, $hardcoded)) {
        $failures[] = 'authentication rate limiter still contains hardcoded '.$hardcoded;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Security policy preflight FAILED:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "PASS: password policy, authentication throttles, retry-after responses and UI guidance use the centralized security settings architecture.\n";
