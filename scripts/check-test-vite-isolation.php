<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$testCase = (string) file_get_contents($root.'/tests/TestCase.php');
$layout = (string) file_get_contents($root.'/resources/views/layouts/app.blade.php');

foreach ([
    'protected function setUp(): void',
    'parent::setUp();',
    '$this->withoutVite();',
] as $needle) {
    if (! str_contains($testCase, $needle)) {
        $errors[] = "Base Tests\\TestCase is missing {$needle}.";
    }
}

// Production must continue using the real Vite integration. The bypass belongs
// only in the PHPUnit base class so missing frontend assets cannot hide a real
// deployment/build issue.
if (! str_contains($layout, "@vite(['resources/css/app.css', 'resources/js/app.js'])")) {
    $errors[] = 'Production app layout no longer uses the real @vite entrypoints.';
}

foreach (['resources/views/layouts/app.blade.php', 'resources/views/layouts/auth.blade.php'] as $file) {
    $contents = (string) file_get_contents($root.'/'.$file);
    if (str_contains($contents, "app()->environment('testing')") || str_contains($contents, "APP_ENV")) {
        $errors[] = "{$file} contains a testing-specific Vite bypass; test isolation must stay in Tests\\TestCase.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Test Vite-isolation regression check failed:\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Test Vite-isolation regression check passed.\n";
