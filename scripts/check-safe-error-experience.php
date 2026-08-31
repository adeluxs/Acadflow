<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'central user-facing error normalizer' => ['app/Support/Errors/UserFacingError.php', 'final class UserFacingError'],
    'request correlation middleware' => ['app/Http/Middleware/RequestCorrelationId.php', 'X-Request-Id'],
    'global exception safety boundary' => ['bootstrap/app.php', 'UserFacingError::fromThrowable'],
    'structured API validation errors' => ['bootstrap/app.php', "'code' => 'VALIDATION_FAILED'"],
    'structured API authentication errors' => ['bootstrap/app.php', "'code' => 'AUTHENTICATION_REQUIRED'"],
    'central feedback partial' => ['resources/views/partials/feedback.blade.php', 'UserFacingError::safeMessage'],
    'app layout uses feedback partial' => ['resources/views/layouts/app.blade.php', "@include('partials.feedback')"],
    'auth layout uses feedback partial' => ['resources/views/layouts/auth.blade.php', "@include('partials.feedback')"],
    'frontend error normalizer' => ['resources/js/error-feedback.js', 'normalizeError'],
    'frontend error normalizer initialized' => ['resources/js/app.js', 'initErrorFeedback()'],
    'AI assistant retry UX' => ['resources/views/ai/assistant.blade.php', 'AI request not completed'],
    'contextual AI retry UX' => ['resources/views/ai/_contextual-assistant.blade.php', 'data-ai-retry'],
    'attendance component retry UX' => ['resources/views/attendance/session.blade.php', 'qr-retry'],
    'gateway test failure is explicit' => ['app/Http/Controllers/Admin/PaymentGatewayController.php', 'PAYMENT_GATEWAY_TEST_FAILED'],
    'gateway calls have bounded timeout' => ['app/Services/PaymentGateway/PaystackGateway.php', 'connectTimeout'],
];

$failures = [];
foreach ($checks as $label => [$relative, $needle]) {
    $path = $root.DIRECTORY_SEPARATOR.$relative;
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false || ! str_contains($content, $needle)) {
        $failures[] = $label.' ('.$relative.')';
    }
}

$resourceFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources'));
foreach ($iterator as $file) {
    if (! $file->isFile()) continue;
    $extension = strtolower($file->getExtension());
    if (! in_array($extension, ['php', 'js', 'vue'], true) && ! str_ends_with($file->getFilename(), '.blade.php')) continue;
    $resourceFiles[] = $file->getPathname();
}

foreach ($resourceFiles as $path) {
    $content = file_get_contents($path) ?: '';
    if (preg_match('/\balert\s*\(/', $content)) {
        $failures[] = 'browser-native alert remains in '.str_replace($root.'/', '', $path);
    }
}

$userFacingPhp = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http'));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') $userFacingPhp[] = $file->getPathname();
}
foreach ($userFacingPhp as $path) {
    $content = file_get_contents($path) ?: '';
    $patterns = [
        '/->with\(\s*[\'\"]error[\'\"]\s*,[^;\n]*->getMessage\(\)/',
        '/response\(\)->json\([^;\n]*->getMessage\(\)/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            // Explicit safeMessage wrappers and secure internal logging are allowed.
            if (! str_contains($content, 'UserFacingError::safeMessage')) {
                $failures[] = 'possible raw exception-message response in '.str_replace($root.'/', '', $path);
            }
            break;
        }
    }
}

$assistant = file_get_contents($root.'/resources/views/ai/assistant.blade.php') ?: '';
if (substr_count($assistant, "const tool = document.getElementById('assistantTool');") !== 1) {
    $failures[] = 'AI assistant script has a duplicate/missing tool declaration';
}

$paystack = file_get_contents($root.'/app/Services/PaymentGateway/PaystackGateway.php') ?: '';
if (preg_match('/[\'\"]message[\'\"]\s*=>[^\n]*\$response->json\(\s*[\'\"]message/', $paystack)) {
    $failures[] = 'Paystack still reflects provider message directly to users';
}

if ($failures !== []) {
    fwrite(STDERR, "Safe error experience preflight FAILED:\n - ".implode("\n - ", array_values(array_unique($failures)))."\n");
    exit(1);
}

echo "PASS: centralized safe errors, structured API failures, request IDs, non-disruptive frontend feedback, and bounded provider transport checks are present.\n";
