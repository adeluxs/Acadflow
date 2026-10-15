<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [];
$failures = [];

$read = static fn (string $path): string => (string) file_get_contents($root.'/'.$path);
$expect = static function (bool $condition, string $message) use (&$checks, &$failures): void {
    if ($condition) $checks[] = $message; else $failures[] = $message;
};

$institutionView = $read('resources/views/institution/onboarding-request.blade.php');
$institutionService = $read('app/Services/InstitutionAdoptionService.php');
$expect(!str_contains($institutionView, 'x-data') && !str_contains($institutionView, 'x-model'), 'institution search does not depend on missing Alpine runtime');
$expect(str_contains($institutionView, 'AbortController') && str_contains($institutionView, 'setTimeout(search, 350)'), 'institution search debounces and aborts stale browser requests');
$expect(str_contains($institutionView, "input.value.trim()") && str_contains($institutionView, "retry.classList.remove('hidden')"), 'institution search trims terms and provides retry feedback');
$expect(str_contains($institutionService, "LOWER(name) LIKE ?") && str_contains($institutionService, "LOWER(COALESCE(short_name"), 'institution search uses portable partial case-insensitive matching');

$aiConfig = $read('config/ai.php');
$external = $read('app/Ai/Providers/ExternalProvider.php');
$manager = $read('app/Ai/AiManager.php');
$gemini = $read('app/Ai/Providers/GeminiProvider.php');
$errors = $read('app/Support/Errors/UserFacingError.php');
$expect(str_contains($aiConfig, 'lifecycle_budget_seconds') && str_contains($aiConfig, 'post_provider_reserve_seconds'), 'AI has an explicit end-to-end lifecycle budget and post-provider reserve');
$expect(str_contains($external, "['_ai_request_id']") && str_contains($external, 'effectiveRequestTimeout'), 'provider transport preserves logical request ID and bounds each attempt');
$expect(str_contains($manager, 'safeReleaseReservation') && str_contains($manager, 'answer preserved'), 'post-provider bookkeeping failures preserve valid AI answers');
$expect(str_contains($gemini, 'AI_EMPTY_RESPONSE') && str_contains($gemini, 'AI_RESPONSE_BLOCKED') && str_contains($gemini, 'AI_RESPONSE_PARSE_FAILED'), 'Gemini HTTP 200 parsing distinguishes empty, blocked and malformed results');
$expect(str_contains($errors, 'AI_REQUEST_TIMEOUT') && str_contains($errors, 'AI_RESPONSE_PARSE_FAILED'), 'new AI failure categories have safe user-facing mappings');

$moneyController = $read('app/Http/Controllers/Admin/MonetizationController.php');
$moneyView = $read('resources/views/admin/monetization/index.blade.php');
$expect(str_contains($moneyController, '$setupCurrency=') && str_contains($moneyController, "'setupCurrency'"), 'Monetization controller resolves and passes setupCurrency');
$expect(!str_contains($moneyView, '$setupInstitutionCurrency'), 'Monetization Blade does not depend on obsolete setupInstitutionCurrency');
$expect(!str_contains($moneyView, '@php') && str_contains($moneyController, '$suggestedTargetMinor=') && str_contains($moneyController, "'suggestedTargetMinor'"), 'Monetization form state is prepared in the controller and explicitly passed to Blade');
$expect(!str_contains($moneyView, '$metricCards') && str_contains($moneyView, "@foreach([['Paid orders',\$metrics['paid_orders']]") && !preg_match('/@foreach\(\[\s*\n/s', $moneyView), 'Monetization metric cards render with the parser-safe inline loop and no undefined temporary variable');

$bladeFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));
$bladeCount = 0;
foreach ($bladeFiles as $file) {
    if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) continue;
    $bladeCount++;
    $text = (string) file_get_contents($file->getPathname());
    $pairs = [
        ['@foreach', '@endforeach'], ['@for', '@endfor'], ['@while', '@endwhile'],
        ['@forelse', '@endforelse'], ['@switch', '@endswitch'],
    ];
    foreach ($pairs as [$open, $close]) {
        if (substr_count($text, $open) !== substr_count($text, $close)) {
            $failures[] = 'Blade directive mismatch in '.str_replace($root.'/', '', $file->getPathname())." ({$open}/{$close})";
        }
    }
}
$expect($bladeCount > 0, "Blade directive balance checked across {$bladeCount} templates");

foreach ([
    'tests/Feature/InstitutionOnboardingSearchRegressionTest.php',
    'tests/Feature/AiResponseLifecycleRegressionTest.php',
    'tests/Unit/AiPostProviderResilienceTest.php',
    'tests/Feature/MonetizationPageRegressionTest.php',
] as $test) $expect(is_file($root.'/'.$test), 'regression test present: '.$test);

if ($failures !== []) {
    fwrite(STDERR, "Four critical fixes preflight: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Four critical fixes preflight: PASS\n";
foreach ($checks as $check) echo " - {$check}\n";
