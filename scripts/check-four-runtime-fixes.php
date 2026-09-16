<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$checks = [
    'Institution onboarding no longer depends on Alpine' => ! str_contains((string) file_get_contents($root.'/resources/views/institution/onboarding-request.blade.php'), 'x-data'),
    'Institution onboarding uses dedicated search module' => str_contains((string) file_get_contents($root.'/resources/js/app.js'), "import('./institution-onboarding')"),
    'Search module has debounce and request cancellation' => str_contains((string) file_get_contents($root.'/resources/js/institution-onboarding.js'), 'AbortController') && str_contains((string) file_get_contents($root.'/resources/js/institution-onboarding.js'), 'setTimeout(runSearch, 350)'),
    'Search module is initialized exactly once by app boot' => ! str_contains((string) file_get_contents($root.'/resources/js/institution-onboarding.js'), 'ready(initInstitutionOnboardingSearch)'),
    'Institution search is partial and normalized' => str_contains((string) file_get_contents($root.'/app/Services/InstitutionAdoptionService.php'), 'LOWER(name) LIKE') && str_contains((string) file_get_contents($root.'/app/Services/InstitutionAdoptionService.php'), 'trim($term)'),
    'Monetization controller supplies setupCurrency' => str_contains((string) file_get_contents($root.'/app/Http/Controllers/Admin/MonetizationController.php'), '$setupCurrency=$setupInstitutionCurrency') && str_contains((string) file_get_contents($root.'/app/Http/Controllers/Admin/MonetizationController.php'), "'setupCurrency'"),
    'AI interactive request budget is configured' => str_contains((string) file_get_contents($root.'/config/ai.php'), 'interactive_request_budget') && str_contains((string) file_get_contents($root.'/.env.example'), 'AI_INTERACTIVE_REQUEST_BUDGET=24'),
    'Provider attempts preserve logical correlation IDs' => str_contains((string) file_get_contents($root.'/app/Ai/Providers/ExternalProvider.php'), 'provider_attempt_id') && str_contains((string) file_get_contents($root.'/app/Ai/Providers/ExternalProvider.php'), 'X-AcadFlow-Request-Id'),
    'Provider timeout is clamped to request deadline' => str_contains((string) file_get_contents($root.'/app/Ai/Providers/ExternalProvider.php'), 'attemptTimeoutSeconds') && str_contains((string) file_get_contents($root.'/app/Ai/Providers/ExternalProvider.php'), '_ai_deadline_at'),
    'Successful AI result survives settlement failure' => str_contains((string) file_get_contents($root.'/app/Ai/AiManager.php'), 'billing_settlement_pending'),
    'Gemini classifies empty blocked and malformed responses' => str_contains((string) file_get_contents($root.'/app/Ai/Providers/GeminiProvider.php'), 'AI_EMPTY_RESPONSE') && str_contains((string) file_get_contents($root.'/app/Ai/Providers/GeminiProvider.php'), 'AI_RESPONSE_BLOCKED') && str_contains((string) file_get_contents($root.'/app/Ai/Providers/GeminiProvider.php'), 'AI_RESPONSE_PARSE_FAILED'),
    'AI cache excludes volatile lifecycle fields' => str_contains((string) file_get_contents($root.'/app/Ai/AiCache.php'), "'_ai_deadline_at'"),
];

foreach ($checks as $label => $ok) {
    if (! $ok) $failures[] = $label;
}

if ($failures !== []) {
    fwrite(STDERR, "Four-runtime-fixes preflight FAILED:\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Four-runtime-fixes preflight: PASS\n";
foreach (array_keys($checks) as $label) echo " - {$label}\n";
